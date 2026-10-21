<?php

/**
 * Reads this site's unit lists and the values its unit columns hold. Read-only.
 *
 * The columns are the ones the units inventory for openemr/openemr#14698 found holding a unit.
 * Three hold option ids of drug_units and one of proc_unit; the others hold free text.
 * form_vitals holds no unit at all (each column has a fixed one), so it isn't here.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\UnitsCheck\Units;

use OpenEMR\Common\Database\QueryUtils;

class SiteReader
{
    /** The lists whose options are units, and any other list whose id says unit. */
    public const UNIT_LISTS = ['drug_units', 'drug_form', 'proc_unit'];

    /** table.column => the list whose option ids it holds (null: free text). */
    public const COLUMNS = [
        'immunizations.amount_administered_unit' => 'drug_units',
        'prescriptions.unit' => 'drug_units',
        'drugs.unit' => 'drug_units',
        'procedure_type.units' => 'proc_unit',
        'procedure_result.units' => null,
        'procedure_specimen.volume_unit' => null,
        'form_observation.ob_unit' => null,
        'form_vitals_calculation_components.value_unit' => null,
    ];

    /** Distinct values read per column, most common first. */
    public const MAX_VALUES = 200;

    /**
     * @return array<string, list<array{option_id: string, title: string, codes: string, active: bool}>>
     */
    public function lists(): array
    {
        $ids = self::UNIT_LISTS;
        foreach (QueryUtils::fetchRecords("SELECT DISTINCT `list_id` FROM `list_options` WHERE `list_id` LIKE '%unit%' ORDER BY `list_id`") as $row) {
            $ids[] = (string)$row['list_id'];
        }
        $out = [];
        foreach (array_values(array_unique($ids)) as $id) {
            $rows = QueryUtils::fetchRecords(
                'SELECT `option_id`, `title`, `codes`, `activity` FROM `list_options` WHERE `list_id` = ? ORDER BY `seq`, `option_id`',
                [$id]
            );
            if ($rows === []) {
                continue;
            }
            $out[$id] = array_map(static fn(array $r): array => [
                'option_id' => (string)$r['option_id'],
                'title' => (string)$r['title'],
                'codes' => (string)($r['codes'] ?? ''),
                'active' => (int)$r['activity'] === 1,
            ], $rows);
        }
        return $out;
    }

    /**
     * Each unit column's distinct values with their row counts, or null for a column this site's
     * OpenEMR doesn't have.
     *
     * @return array<string, array{list: ?string, rows: int, values: list<array{value: ?string, rows: int}>}|null>
     */
    public function columns(): array
    {
        $out = [];
        foreach (self::COLUMNS as $column => $list) {
            [$table, $field] = explode('.', $column);
            $exists = QueryUtils::fetchRecords(
                'SELECT COUNT(*) AS `n` FROM `information_schema`.`columns` WHERE `table_schema` = DATABASE() AND `table_name` = ? AND `column_name` = ?',
                [$table, $field]
            );
            if ((int)($exists[0]['n'] ?? 0) === 0) {
                $out[$column] = null;
                continue;
            }
            // Table and column names come from COLUMNS above, never from the request. Grouped on the
            // binary value: an installer-created database's collation folds case, and PG and pg are
            // different units.
            $total = QueryUtils::fetchRecords("SELECT COUNT(*) AS `n` FROM `$table`");
            $values = QueryUtils::fetchRecords(
                "SELECT BINARY `$field` AS `value`, COUNT(*) AS `n` FROM `$table` GROUP BY BINARY `$field` ORDER BY `n` DESC LIMIT " . self::MAX_VALUES
            );
            $out[$column] = [
                'list' => $list,
                'rows' => (int)($total[0]['n'] ?? 0),
                'values' => array_map(static fn(array $r): array => [
                    'value' => $r['value'] === null ? null : (string)$r['value'],
                    'rows' => (int)$r['n'],
                ], $values),
            ];
        }
        return $out;
    }
}
