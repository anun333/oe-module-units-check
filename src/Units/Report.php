<?php

/**
 * What this site's units are in UCUM terms: each unit list option and each value its unit columns
 * hold, with the UCUM code an export would send for it, or why it would go out as text only.
 * Built from what SiteReader read, so it can be tested without a database.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\UnitsCheck\Units;

final class Report
{
    /**
     * @param array<string, list<array{option_id: string, title: string, codes: string, active: bool}>> $lists
     * @param array<string, array{list: ?string, rows: int, values: list<array{value: ?string, rows: int}>}|null> $columns
     * @return array{
     *     source: string,
     *     lists: array<string, list<array{option_id: string, title: string, codes: string, active: bool, status: string, code: ?string, why: string, suggest: ?string}>>,
     *     columns: array<string, array{list: ?string, rows: int, values: list<array{value: ?string, rows: int, option: ?string, status: string, code: ?string, why: string}>, with_code: int, text_only: int, unchecked: int, empty: int}|null>,
     *     totals: array{options: int, options_with_code: int, suggestions: int, rows_with_code: int, rows_text_only: int, rows_unchecked: int}
     * }
     */
    public static function build(Resolver $resolver, array $lists, array $columns): array
    {
        $out = ['source' => $resolver->source(), 'lists' => [], 'columns' => []];
        $totals = ['options' => 0, 'options_with_code' => 0, 'suggestions' => 0, 'rows_with_code' => 0, 'rows_text_only' => 0, 'rows_unchecked' => 0];
        $byList = [];
        foreach ($lists as $listId => $options) {
            foreach ($options as $o) {
                $r = $resolver->resolveOption($o['title'], $o['codes']);
                // a code to offer: the one the title stands for, when the codes field has none yet
                $suggest = $r->status !== Resolution::LIST_CODE && $r->code !== null ? $r->code : null;
                $row = $o + ['status' => $r->status, 'code' => $r->code, 'why' => $r->why, 'suggest' => $suggest];
                $out['lists'][$listId][] = $row;
                $byList[$listId][$o['option_id']] = $row;
                $totals['options']++;
                $totals['options_with_code'] += $r->status === Resolution::LIST_CODE ? 1 : 0;
                $totals['suggestions'] += $suggest !== null ? 1 : 0;
            }
        }
        foreach ($columns as $column => $c) {
            if ($c === null) {
                $out['columns'][$column] = null;
                continue;
            }
            $values = [];
            $counts = ['with_code' => 0, 'text_only' => 0, 'unchecked' => 0, 'empty' => 0];
            foreach ($c['values'] as $v) {
                $option = $c['list'] !== null && $v['value'] !== null ? ($byList[$c['list']][$v['value']] ?? null) : null;
                if ($option !== null) {
                    // an option id: the export sends the option's UCUM code, or its title as text
                    $r = new Resolution((string)$option['status'], $option['code'], $option['why']);
                    $label = $option['title'];
                } else {
                    // free text, or a value written by another path (a FHIR client's unit code)
                    $r = $resolver->resolve($v['value']);
                    $label = null;
                }
                $bucket = $r->status === Resolution::EMPTY ? 'empty' : ($r->code !== null ? 'with_code' : ($r->status === Resolution::UNCHECKED ? 'unchecked' : 'text_only'));
                $counts[$bucket] += $v['rows'];
                $values[] = ['value' => $v['value'], 'rows' => $v['rows'], 'option' => $label, 'status' => $r->status, 'code' => $r->code, 'why' => $r->why];
            }
            $out['columns'][$column] = ['list' => $c['list'], 'rows' => $c['rows'], 'values' => $values] + $counts;
            $totals['rows_with_code'] += $counts['with_code'];
            $totals['rows_text_only'] += $counts['text_only'];
            $totals['rows_unchecked'] += $counts['unchecked'];
        }
        $out['totals'] = $totals;
        return $out;
    }
}
