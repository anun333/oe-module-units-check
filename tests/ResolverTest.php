<?php

/**
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\UnitsCheck\Tests;

use OpenHealthcare\UnitsCheck\Units\Resolution;
use OpenHealthcare\UnitsCheck\Units\Resolver;
use OpenHealthcare\UnitsCheck\Units\Spellings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResolverTest extends TestCase
{
    /**
     * Without an engine, as on every release up to 8.4.1: [input, status, code].
     *
     * @return array<string, array{mixed, string, ?string}>
     */
    public static function withoutEngine(): array
    {
        return [
            'a verified code' => ['mg', Resolution::UCUM, 'mg'],
            'a verified code with brackets' => ['mm[Hg]', Resolution::UCUM, 'mm[Hg]'],
            'a vitals spelling' => ['lb', Resolution::MAPPED, '[lb_av]'],
            'a drug_units title' => ['mcg', Resolution::MAPPED, 'ug'],
            'capitals mean picograms' => ['PG', Resolution::MAPPED, 'pg'],
            'a proc_unit title' => ['Thous/CU.MM', Resolution::MAPPED, '10*3/mm3'],
            'K/uL is thousands' => ['K/uL', Resolution::MAPPED, '10*3/uL'],
            'an option id' => ['9', Resolution::REFUSED, null],
            'F is farad' => ['F', Resolution::REFUSED, null],
            'unknown capitals' => ['HMOL/L', Resolution::REFUSED, null],
            'a dose form' => ['tablet', Resolution::UNCHECKED, null],
            'spaces' => ['  mg ', Resolution::UCUM, 'mg'],
            'empty' => ['', Resolution::EMPTY, null],
            'null' => [null, Resolution::EMPTY, null],
        ];
    }

    #[DataProvider('withoutEngine')]
    public function testWithoutAnEngine(mixed $unit, string $status, ?string $code): void
    {
        $r = (new Resolver())->resolve($unit);
        $this->assertSame($status, $r->status);
        $this->assertSame($code, $r->code);
    }

    public function testWithTheEngineEveryStringIsChecked(): void
    {
        $engine = static fn(string $s): bool => in_array($s, ['mmol/L', 'mg', 'PG', '9', 'F'], true);
        $resolver = new Resolver($engine);
        $this->assertSame('core engine', $resolver->source());
        $this->assertSame([Resolution::UCUM, 'mmol/L'], self::pair($resolver->resolve('mmol/L')));
        $this->assertSame([Resolution::NOT_UCUM, null], self::pair($resolver->resolve('tablet')));
        // the engine accepts PG, 9 and F; the rules still refuse or map them
        $this->assertSame([Resolution::MAPPED, 'pg'], self::pair($resolver->resolve('PG')));
        $this->assertSame([Resolution::REFUSED, null], self::pair($resolver->resolve('9')));
        $this->assertSame([Resolution::REFUSED, null], self::pair($resolver->resolve('F')));
    }

    public function testCoreLayerWinsWhenPresent(): void
    {
        $core = static fn(string $s): ?string => ['lb' => '[lb_av]', 'mg' => 'mg'][$s] ?? null;
        $resolver = new Resolver(static fn(string $s): bool => true, $core);
        $this->assertSame('core layer', $resolver->source());
        $this->assertSame([Resolution::MAPPED, '[lb_av]'], self::pair($resolver->resolve('lb')));
        $this->assertSame([Resolution::UCUM, 'mg'], self::pair($resolver->resolve('mg')));
        $this->assertSame([Resolution::REFUSED, null], self::pair($resolver->resolve('9')));
        $this->assertSame([Resolution::NOT_UCUM, null], self::pair($resolver->resolve('tablet')));
    }

    public function testListOptions(): void
    {
        $plain = new Resolver();
        $this->assertSame([Resolution::LIST_CODE, 'ug'], self::pair($plain->resolveOption('micrograms', 'NCI-CONCEPT-ID:C1;UCUM:ug')));
        $this->assertSame([Resolution::MAPPED, 'ug'], self::pair($plain->resolveOption('mcg', 'NCI-CONCEPT-ID:C1')));
        $this->assertSame([Resolution::UNCHECKED, null], self::pair($plain->resolveOption('x', 'UCUM:furlong')));
        $withEngine = new Resolver(static fn(string $s): bool => $s === 'ug');
        $this->assertSame([Resolution::NOT_UCUM, null], self::pair($withEngine->resolveOption('x', 'UCUM:furlong')));
    }

    public function testAcceptsOnlyCodesItCanConfirm(): void
    {
        $plain = new Resolver();
        $this->assertTrue($plain->acceptsCode('ug'));
        $this->assertTrue($plain->acceptsCode(' 10*3/uL '));
        $this->assertFalse($plain->acceptsCode('9'));
        $this->assertFalse($plain->acceptsCode('PG'));
        // capitals the module verified as UCUM codes: units per litre, litre
        $this->assertTrue($plain->acceptsCode('U/L'));
        $this->assertTrue($plain->acceptsCode('L'));
        $engine = new Resolver(static fn(string $s): bool => true);
        $this->assertFalse($engine->acceptsCode('PG'), 'the engine accepts PG as petagauss; the module still refuses it');
        $this->assertTrue($engine->acceptsCode('U/L'));
        $this->assertFalse($plain->acceptsCode('furlong'));
        $this->assertFalse($plain->acceptsCode(''));
        $this->assertTrue((new Resolver(static fn(string $s): bool => $s === 'umol/mol'))->acceptsCode('umol/mol'));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function codesFields(): array
    {
        return [
            'empty' => ['', 'ug', 'UCUM:ug'],
            'after another code' => ['NCI-CONCEPT-ID:C28253', 'mg', 'NCI-CONCEPT-ID:C28253;UCUM:mg'],
            'replaces a UCUM entry' => ['UCUM:mg;NCI-CONCEPT-ID:C28253', 'ug', 'NCI-CONCEPT-ID:C28253;UCUM:ug'],
            'lower-case prefix' => ['ucum:mg', 'ug', 'UCUM:ug'],
            'stray separators' => [';NCI-CONCEPT-ID:C1; ;', 'ug', 'NCI-CONCEPT-ID:C1;UCUM:ug'],
        ];
    }

    #[DataProvider('codesFields')]
    public function testWithUcumEntry(string $codes, string $code, string $expected): void
    {
        $this->assertSame($expected, Resolver::withUcumEntry($codes, $code));
        $this->assertSame($code, Resolver::ucumEntry($expected));
    }

    public function testEveryCodeTheModuleTrustsWasVerified(): void
    {
        $this->assertGreaterThan(80, count(Spellings::VERIFIED));
        $trusted = array_merge(array_values(Spellings::SPELLINGS), array_values(Spellings::READ_AS), Spellings::COMMON);
        foreach (array_unique($trusted) as $code) {
            $this->assertContains($code, Spellings::VERIFIED, "$code was not checked by the engine; run tools/sync_spellings.php");
        }
        foreach (array_keys(Spellings::SPELLINGS) as $key) {
            $this->assertSame(mb_strtolower($key), $key);
        }
    }

    /**
     * @return array{string, ?string}
     */
    private static function pair(Resolution $r): array
    {
        return [$r->status, $r->code];
    }
}
