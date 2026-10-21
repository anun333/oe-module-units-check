<?php

/**
 * Sets the UCUM code in a unit list option's codes field (UCUM:mg), keeping its other codes, so
 * that exports send that option as UCUM. The only thing the module writes.
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

class ListWriter
{
    public function __construct(private readonly Resolver $resolver)
    {
    }

    /**
     * @return array{ok: bool, before: string, after: string, message: string}
     */
    public function setCode(string $listId, string $optionId, string $code): array
    {
        $code = trim($code);
        if (!in_array($listId, $this->unitLists(), true)) {
            return ['ok' => false, 'before' => '', 'after' => '', 'message' => 'not a unit list'];
        }
        if (!$this->resolver->acceptsCode($code)) {
            return ['ok' => false, 'before' => '', 'after' => '', 'message' => 'not a UCUM code this OpenEMR can confirm'];
        }
        $row = QueryUtils::fetchRecords('SELECT `codes` FROM `list_options` WHERE `list_id` = ? AND `option_id` = ?', [$listId, $optionId])[0] ?? null;
        if ($row === null) {
            return ['ok' => false, 'before' => '', 'after' => '', 'message' => 'no such option'];
        }
        $before = (string)($row['codes'] ?? '');
        $after = Resolver::withUcumEntry($before, $code);
        if ($after === $before) {
            return ['ok' => true, 'before' => $before, 'after' => $after, 'message' => 'already set'];
        }
        QueryUtils::sqlStatementThrowException(
            'UPDATE `list_options` SET `codes` = ? WHERE `list_id` = ? AND `option_id` = ?',
            [$after, $listId, $optionId]
        );
        return ['ok' => true, 'before' => $before, 'after' => $after, 'message' => 'set'];
    }

    /** @return list<string> */
    private function unitLists(): array
    {
        $ids = SiteReader::UNIT_LISTS;
        foreach (QueryUtils::fetchRecords("SELECT DISTINCT `list_id` FROM `list_options` WHERE `list_id` LIKE '%unit%'") as $row) {
            $ids[] = (string)$row['list_id'];
        }
        return $ids;
    }
}
