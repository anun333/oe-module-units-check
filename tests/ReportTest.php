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

use OpenHealthcare\UnitsCheck\Units\Report;
use OpenHealthcare\UnitsCheck\Units\Resolution;
use OpenHealthcare\UnitsCheck\Units\Resolver;
use PHPUnit\Framework\TestCase;

final class ReportTest extends TestCase
{
    public function testListsAndColumns(): void
    {
        $lists = ['drug_units' => [
            ['option_id' => '7', 'title' => 'mcg', 'codes' => '', 'active' => true],
            ['option_id' => '9', 'title' => 'mL', 'codes' => 'UCUM:mL', 'active' => true],
            ['option_id' => '20', 'title' => 'puffs', 'codes' => '', 'active' => true],
        ]];
        $columns = [
            'immunizations.amount_administered_unit' => ['list' => 'drug_units', 'rows' => 10, 'values' => [
                ['value' => '9', 'rows' => 5],      // an option id: reads as mL from the codes field
                ['value' => '7', 'rows' => 2],      // an option id whose title maps
                ['value' => 'mg', 'rows' => 2],     // a FHIR client's unit code, not an option id
                ['value' => null, 'rows' => 1],
            ]],
            'procedure_result.units' => ['list' => null, 'rows' => 4, 'values' => [
                ['value' => 'MG/DL', 'rows' => 3],
                ['value' => 'furlongs', 'rows' => 1],
            ]],
            'form_observation.ob_unit' => null,
        ];
        $r = Report::build(new Resolver(), $lists, $columns);

        $this->assertSame('module tables', $r['source']);
        [$mcg, $ml, $puffs] = $r['lists']['drug_units'];
        $this->assertSame([Resolution::MAPPED, 'ug', 'ug'], [$mcg['status'], $mcg['code'], $mcg['suggest']]);
        $this->assertSame([Resolution::LIST_CODE, 'mL', null], [$ml['status'], $ml['code'], $ml['suggest']]);
        $this->assertSame([Resolution::UNCHECKED, null, null], [$puffs['status'], $puffs['code'], $puffs['suggest']]);
        $this->assertSame(['options' => 3, 'options_with_code' => 1, 'suggestions' => 1, 'rows_with_code' => 12, 'rows_text_only' => 0, 'rows_unchecked' => 1], $r['totals']);

        $imm = $r['columns']['immunizations.amount_administered_unit'];
        $this->assertNotNull($imm);
        $this->assertSame(['mL', 'mcg', null, null], array_column($imm['values'], 'option'));
        $this->assertSame(['mL', 'ug', 'mg', null], array_column($imm['values'], 'code'));
        $this->assertSame([9, 0, 0, 1], [$imm['with_code'], $imm['text_only'], $imm['unchecked'], $imm['empty']]);

        $labs = $r['columns']['procedure_result.units'];
        $this->assertNotNull($labs);
        $this->assertSame(['mg/dL', null], array_column($labs['values'], 'code'));
        $this->assertSame([3, 0, 1], [$labs['with_code'], $labs['text_only'], $labs['unchecked']]);
        $this->assertNull($r['columns']['form_observation.ob_unit']);
    }

    public function testWithTheEngineUnknownStringsAreTextOnly(): void
    {
        $columns = ['procedure_result.units' => ['list' => null, 'rows' => 1, 'values' => [['value' => 'furlongs', 'rows' => 1]]]];
        $r = Report::build(new Resolver(static fn(string $s): bool => false), [], $columns);
        $this->assertSame(1, $r['totals']['rows_text_only']);
        $this->assertSame(0, $r['totals']['rows_unchecked']);
    }
}
