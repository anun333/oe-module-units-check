<?php

/**
 * What a unit string, or a unit list option, is in UCUM terms.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\UnitsCheck\Units;

final class Resolution
{
    /** Valid UCUM as written. */
    public const UCUM = 'ucum';
    /** A known spelling (lb, mcg, MG/DL) of the UCUM code given. */
    public const MAPPED = 'mapped';
    /** A list option whose codes field carries this UCUM code. */
    public const LIST_CODE = 'list';
    /** The engine accepts it, but with a meaning OpenEMR doesn't intend: a bare number, or capitals (PG is petagauss). */
    public const REFUSED = 'refused';
    /** Not UCUM, and no known spelling of one: it goes out as text. */
    public const NOT_UCUM = 'not_ucum';
    /** This OpenEMR has no UCUM engine, and the string isn't one the module knows. */
    public const UNCHECKED = 'unchecked';
    public const EMPTY = 'empty';

    public function __construct(
        public readonly string $status,
        public readonly ?string $code = null,
        public readonly string $why = '',
    ) {
    }

    /** Whether an export would send this as a UCUM code. */
    public function hasCode(): bool
    {
        return $this->code !== null;
    }
}
