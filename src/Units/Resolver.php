<?php

/**
 * Reads a stored or listed unit string as UCUM, the way the shared unit layer proposed for
 * OpenEMR core reads it (openemr/openemr#14698), on any OpenEMR this module runs on:
 *
 *   OpenEMR with that layer (OpenEMR\Common\Units\UcumCode)   core's own reading
 *   OpenEMR with core's UCUM engine only (master since 2026-10-11)   the engine, with the same rules
 *   OpenEMR without either (every release up to 8.4.1)        the copied tables and VERIFIED codes
 *
 * The rules: a bare number is never a unit (in OpenEMR it is a list option id), a spelling with
 * no lower-case letter outside brackets is never trusted as written (UCUM is case-sensitive: PG is
 * petagauss, F is farad), and the lab spellings in READ_AS are read as the table says.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\UnitsCheck\Units;

final class Resolver
{
    public const CORE_LAYER = 'OpenEMR\\Common\\Units\\UcumCode';
    public const CORE_ENGINE = 'OpenEMR\\Cqm\\Cql\\Ucum\\Ucum';

    /**
     * @param (\Closure(string): bool)|null $isValid core's engine, when this OpenEMR has it
     * @param (\Closure(string): ?string)|null $coreOf core's UcumCode::of(), when this OpenEMR has it
     */
    public function __construct(private readonly ?\Closure $isValid = null, private readonly ?\Closure $coreOf = null)
    {
    }

    public static function forThisOpenEmr(): self
    {
        $isValid = null;
        $coreOf = null;
        if (class_exists(self::CORE_ENGINE)) {
            $engine = call_user_func([self::CORE_ENGINE, 'instance']);
            $isValid = static fn(string $s): bool => (bool)$engine->isValid($s);
        }
        if (class_exists(self::CORE_LAYER)) {
            $coreOf = static function (string $s): ?string {
                $code = call_user_func([self::CORE_LAYER, 'of'], $s);
                return is_string($code) ? $code : null;
            };
        }
        return new self($isValid, $coreOf);
    }

    /** Which reading this OpenEMR gets: 'core layer', 'core engine' or 'module tables'. */
    public function source(): string
    {
        return $this->coreOf !== null ? 'core layer' : ($this->isValid !== null ? 'core engine' : 'module tables');
    }

    public function resolve(mixed $unit): Resolution
    {
        $unit = is_string($unit) || is_int($unit) || is_float($unit) ? trim((string)$unit) : '';
        if ($unit === '') {
            return new Resolution(Resolution::EMPTY);
        }
        if ($this->coreOf !== null) {
            $code = ($this->coreOf)($unit);
            if ($code === $unit) {
                return new Resolution(Resolution::UCUM, $code);
            }
            if ($code !== null) {
                return new Resolution(Resolution::MAPPED, $code);
            }
            return $this->notAUnit($unit);
        }
        if (isset(Spellings::READ_AS[$unit])) {
            return new Resolution(Resolution::MAPPED, Spellings::READ_AS[$unit], 'a lab spelling UCUM reads as something else');
        }
        $legacy = self::isLegacySpelling($unit);
        if (!$legacy && $this->isValidUcum($unit)) {
            return new Resolution(Resolution::UCUM, $unit);
        }
        $mapped = Spellings::SPELLINGS[mb_strtolower($unit)] ?? null;
        if ($mapped !== null) {
            return new Resolution(Resolution::MAPPED, $mapped);
        }
        if ($legacy) {
            return $this->notAUnit($unit);
        }
        return $this->isValid !== null
            ? new Resolution(Resolution::NOT_UCUM, null, 'not UCUM and not a known spelling')
            : new Resolution(Resolution::UNCHECKED, null, 'this OpenEMR has no UCUM engine to check it');
    }

    /**
     * A unit list option: the UCUM code in its codes field (UCUM:mg), or else its title read as a unit.
     */
    public function resolveOption(mixed $title, mixed $codes): Resolution
    {
        $listed = self::ucumEntry($codes);
        if ($listed !== null) {
            if ($this->isValidUcum($listed)) {
                return new Resolution(Resolution::LIST_CODE, $listed);
            }
            if ($this->isValid !== null || $this->coreOf !== null) {
                return new Resolution(Resolution::NOT_UCUM, null, 'the codes field says UCUM:' . $listed . ', which is not valid UCUM');
            }
            return new Resolution(Resolution::UNCHECKED, null, 'the codes field says UCUM:' . $listed . '; this OpenEMR has no UCUM engine to check it');
        }
        return $this->resolve($title);
    }

    /**
     * Whether a code may go into a codes field: valid UCUM as written, by the engine or, without
     * one, in VERIFIED. A bare number is refused, and so is an all-capitals code the module doesn't
     * know as one (PG is petagauss; U/L and L are fine).
     */
    public function acceptsCode(string $code): bool
    {
        $code = trim($code);
        if ($code === '' || preg_match('/^[0-9.]+$/', $code) === 1 || !$this->isValidUcum($code)) {
            return false;
        }
        return !self::isLegacySpelling($code) || in_array($code, Spellings::VERIFIED, true);
    }

    /** The UCUM entry in a list option's codes field (NCI-CONCEPT-ID:C28253;UCUM:mg), or null. */
    public static function ucumEntry(mixed $codes): ?string
    {
        foreach (explode(';', is_string($codes) ? $codes : '') as $entry) {
            $parts = explode(':', trim($entry), 2);
            if (count($parts) === 2 && strcasecmp($parts[0], 'UCUM') === 0 && trim($parts[1]) !== '') {
                return trim($parts[1]);
            }
        }
        return null;
    }

    /**
     * A codes field with its UCUM entry set to the code, keeping every other entry.
     */
    public static function withUcumEntry(string $codes, string $code): string
    {
        $kept = [];
        foreach (explode(';', $codes) as $entry) {
            $entry = trim($entry);
            $parts = explode(':', $entry, 2);
            if ($entry === '' || (count($parts) === 2 && strcasecmp($parts[0], 'UCUM') === 0)) {
                continue;
            }
            $kept[] = $entry;
        }
        $kept[] = 'UCUM:' . trim($code);
        return implode(';', $kept);
    }

    private function isValidUcum(string $unit): bool
    {
        if ($this->isValid !== null) {
            return ($this->isValid)($unit);
        }
        return in_array($unit, Spellings::VERIFIED, true);
    }

    private function notAUnit(string $unit): Resolution
    {
        if (preg_match('/^[0-9.]+$/', $unit) === 1) {
            return new Resolution(Resolution::REFUSED, null, 'a bare number: in OpenEMR, a list option id');
        }
        if (self::isLegacySpelling($unit)) {
            return new Resolution(Resolution::REFUSED, null, 'capitals: UCUM is case-sensitive, so this would mean something else');
        }
        return new Resolution(Resolution::NOT_UCUM, null, 'not UCUM and not a known spelling');
    }

    /**
     * A bare number, or a spelling with letters but none in lower case, outside UCUM's brackets and braces.
     */
    private static function isLegacySpelling(string $unit): bool
    {
        if (preg_match('/^[0-9.]+$/', $unit) === 1) {
            return true;
        }
        if (preg_match('/[\[\]{}]/', $unit) === 1) {
            return false;
        }
        return preg_match('/[A-Za-z]/', $unit) === 1 && preg_match('/[a-z]/', $unit) === 0;
    }
}
