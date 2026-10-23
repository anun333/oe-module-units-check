<?php

/**
 * Units Check: what this site's units are in UCUM, the unit codes FHIR, C-CDA, eCQMs and HL7 v2
 * exchange. For each unit list option and each value the unit columns hold, the UCUM code an
 * export would send, or why it would go out as text only. An administrator can add a UCUM code
 * to a list option (UCUM:ug in its codes field); that is the only thing the module changes.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../globals.php';

use OpenEMR\Core\Header;
use OpenHealthcare\UnitsCheck\Compat\OpenEmr;
use OpenHealthcare\UnitsCheck\Units\ListWriter;
use OpenHealthcare\UnitsCheck\Units\Report;
use OpenHealthcare\UnitsCheck\Units\Resolution;
use OpenHealthcare\UnitsCheck\Units\Resolver;
use OpenHealthcare\UnitsCheck\Units\SiteReader;

// The check OpenEMR's own list editor makes.
if (!OpenEmr::isAdministrator()) {
    OpenEmr::deny('ACL check failed for admin/super: Units Check', xl('Units Check'));
}
$resolver = Resolver::forThisOpenEmr();
$site = new SiteReader();
$messages = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    OpenEmr::requireCsrf($_POST['csrf_token_form'] ?? '');
    $writer = new ListWriter($resolver);
    $changes = [];
    if (($_POST['action'] ?? '') === 'set') {
        $changes[] = [(string)($_POST['list_id'] ?? ''), (string)($_POST['option_id'] ?? ''), (string)($_POST['code'] ?? '')];
    } elseif (($_POST['action'] ?? '') === 'set_all') {
        // every suggestion on the page as it was shown, recomputed here rather than taken from the request
        foreach (Report::build($resolver, $site->lists(), [])['lists'] as $listId => $options) {
            foreach ($options as $o) {
                if ($o['suggest'] !== null) {
                    $changes[] = [$listId, $o['option_id'], $o['suggest']];
                }
            }
        }
    }
    foreach ($changes as [$listId, $optionId, $code]) {
        $result = $writer->setCode($listId, $optionId, $code);
        if ($result['ok'] && $result['message'] === 'set') {
            OpenEmr::audit('other', 'Units Check: list ' . $listId . ' option ' . $optionId . ' codes "' . $result['before'] . '" -> "' . $result['after'] . '"');
            $messages[] = ['success', sprintf(xl('%1$s, option %2$s: UCUM code set to %3$s'), $listId, $optionId, trim($code))];
        } elseif (!$result['ok']) {
            $why = [
                'not a unit list' => xl('not a unit list'),
                'not a UCUM code this OpenEMR can confirm' => xl('not a UCUM code this OpenEMR can confirm'),
                'no such option' => xl('no such option'),
            ][$result['message']] ?? $result['message'];
            $messages[] = ['danger', sprintf(xl('%1$s, option %2$s: not changed, %3$s'), $listId, $optionId, $why)];
        }
    }
}

$report = Report::build($resolver, $site->lists(), $site->columns());

if (($_GET['format'] ?? '') === 'json') {
    // unit strings and counts only: no patient identifiers, no clinical values
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="units-check.json"');
    echo json_encode(['openemr' => OpenEmr::version(), 'generated' => date('c')] + $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$statusText = [
    Resolution::UCUM => xl('Valid UCUM as written'),
    Resolution::MAPPED => xl('A known spelling of this code'),
    Resolution::LIST_CODE => xl('From the codes field'),
    Resolution::REFUSED => xl('No UCUM code'),
    Resolution::NOT_UCUM => xl('No UCUM code'),
    Resolution::UNCHECKED => xl('Cannot check on this OpenEMR'),
    Resolution::EMPTY => xl('Empty'),
];
$sourceText = [
    'core layer' => xl('This OpenEMR has the shared unit layer, and its own reading is used.'),
    'core engine' => xl('This OpenEMR has the UCUM engine, which checks every string.'),
    'module tables' => xl('This OpenEMR has no UCUM engine (every release up to 8.4.1). Strings are checked against the codes the module carries, which the engine verified; anything else is marked as not checkable here.'),
];
$token = OpenEmr::csrfToken();
$t = $report['totals'];
?>
<!DOCTYPE html>
<?php // The module's own text is written in English. ?>
<html lang="en">
<head>
    <title><?php echo xlt('Units Check'); ?></title>
    <?php Header::setupHeader(); ?>
    <style>
        .uc-table td, .uc-table th { font-size: .85rem; vertical-align: middle; }
        .uc-code { font-family: monospace; }
        .uc-inline { display: inline-flex; gap: .25rem; align-items: center; }
        .uc-inline input { width: 9rem; }
    </style>
    <style>/* Bootstrap's code colour is 3.7:1 on these backgrounds; this one is over 5.6:1 (WCAG 1.4.3). */ code { color: #b5175b; }</style>
    <style>/* Links in running text are underlined, not told apart by colour alone (WCAG 1.4.1). */ p a, li a { text-decoration: underline; }</style>
</head>
<body class="body_top">
<div class="container-fluid py-3">
    <h1 class="h4"><?php echo xlt('Units Check'); ?></h1>
    <p class="small text-muted"><?php echo xlt('What this site keeps as units, in UCUM: the unit codes FHIR, C-CDA, eCQMs and HL7 v2 exchange. For each unit list option and each value the unit fields hold, the UCUM code an export sends, or why it goes out as text only.'); ?>
        <?php echo text($sourceText[$report['source']] ?? ''); ?></p>
    <?php foreach ($messages as [$kind, $text]) { ?><div class="alert alert-<?php echo attr($kind); ?> py-1 small"><?php echo text($text); ?></div><?php } ?>

    <div class="row mb-3" id="summary">
        <div class="col-md-4"><div class="border rounded p-2"><div class="h5 mb-0"><?php echo text($t['options_with_code'] . ' / ' . $t['options']); ?></div>
            <div class="small text-muted"><?php echo xlt('Unit list options with a UCUM code in their codes field'); ?></div></div></div>
        <div class="col-md-4"><div class="border rounded p-2"><div class="h5 mb-0"><?php echo text((string)$t['suggestions']); ?></div>
            <div class="small text-muted"><?php echo xlt('Options whose title gives a UCUM code that is not set yet'); ?></div></div></div>
        <div class="col-md-4"><div class="border rounded p-2"><div class="h5 mb-0"><?php echo text($t['rows_with_code'] . ' / ' . $t['rows_text_only'] . ' / ' . $t['rows_unchecked']); ?></div>
            <div class="small text-muted"><?php echo xlt('Stored rows with a UCUM code, text only, not checkable here'); ?></div></div></div>
    </div>
    <p class="small"><a href="?format=json" id="download"><?php echo xlt('Download this report as JSON'); ?></a>
        <span class="text-muted"><?php echo xlt('(unit strings and counts only, no patient data)'); ?></span></p>

    <h2 class="h5 mt-3"><?php echo xlt('Unit lists'); ?></h2>
    <p class="small text-muted"><?php echo xlt('Exports read an option UCUM code from its codes field first, then from its title. Dose forms such as tablet are not units and need no code.'); ?></p>
    <?php if ($t['suggestions'] > 0) { ?>
    <form method="post" class="mb-2" id="set-all">
        <input type="hidden" name="csrf_token_form" value="<?php echo attr($token); ?>">
        <input type="hidden" name="action" value="set_all">
        <button class="btn btn-sm btn-primary" type="submit"><?php echo text(sprintf(xl('Add all %d suggested codes'), $t['suggestions'])); ?></button>
    </form>
    <?php } ?>
    <?php foreach ($report['lists'] as $listId => $options) { ?>
        <h3 class="h6 mt-3"><code><?php echo text($listId); ?></code></h3>
        <table class="table table-sm uc-table" data-list="<?php echo attr($listId); ?>">
            <thead><tr><th><?php echo xlt('Option'); ?></th><th><?php echo xlt('Title'); ?></th><th><?php echo xlt('Codes'); ?></th>
                <th><?php echo xlt('UCUM'); ?></th><th><?php echo xlt('Why'); ?></th><th><?php echo xlt('Set the UCUM code'); ?></th></tr></thead>
            <tbody>
            <?php foreach ($options as $o) { ?>
                <tr data-option="<?php echo attr($o['option_id']); ?>" data-status="<?php echo attr($o['status']); ?>"<?php echo $o['active'] ? '' : ' class="text-muted"'; ?>>
                    <td><code><?php echo text($o['option_id']); ?></code></td>
                    <td><?php echo text($o['title']); ?></td>
                    <td class="uc-code"><?php echo text($o['codes']); ?></td>
                    <td class="uc-code"><?php echo text($o['code'] ?? '—'); ?></td>
                    <td><?php echo text($statusText[$o['status']] ?? $o['status']); ?><?php if ($o['why'] !== '') { ?><div class="text-muted"><?php echo text($o['why']); ?></div><?php } ?></td>
                    <td>
                        <form method="post" class="uc-inline">
                            <input type="hidden" name="csrf_token_form" value="<?php echo attr($token); ?>">
                            <input type="hidden" name="action" value="set">
                            <input type="hidden" name="list_id" value="<?php echo attr($listId); ?>">
                            <input type="hidden" name="option_id" value="<?php echo attr($o['option_id']); ?>">
                            <input class="form-control form-control-sm uc-code" type="text" name="code" value="<?php echo attr($o['suggest'] ?? ''); ?>"
                                aria-label="<?php echo xla('UCUM code'); ?>" placeholder="<?php echo xla('UCUM code'); ?>">
                            <button class="btn btn-sm btn-outline-primary" type="submit"><?php echo xlt('Set'); ?></button>
                        </form>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    <?php } ?>

    <h2 class="h5 mt-4"><?php echo xlt('Stored values'); ?></h2>
    <p class="small text-muted"><?php echo xlt('The distinct values each unit field holds, most common first. A list option id reads as its option. The Vitals form keeps each measurement in a fixed unit (pounds, inches, degrees Fahrenheit), so it has nothing to map.'); ?></p>
    <?php foreach ($report['columns'] as $column => $c) { ?>
        <h3 class="h6 mt-3"><code><?php echo text($column); ?></code>
            <?php if ($c === null) { ?><span class="small text-muted"><?php echo xlt('not in this OpenEMR'); ?></span><?php } else { ?>
            <span class="small text-muted"><?php echo text(sprintf(xl('%1$d rows: %2$d with a UCUM code, %3$d text only, %4$d not checkable here, %5$d empty'), $c['rows'], $c['with_code'], $c['text_only'], $c['unchecked'], $c['empty'])); ?></span><?php } ?></h3>
        <?php if ($c !== null && $c['values'] !== []) { ?>
        <table class="table table-sm uc-table" data-column="<?php echo attr($column); ?>">
            <thead><tr><th><?php echo xlt('Value'); ?></th><th><?php echo xlt('Option'); ?></th><th><?php echo xlt('Rows'); ?></th><th><?php echo xlt('UCUM'); ?></th><th><?php echo xlt('Why'); ?></th></tr></thead>
            <tbody>
            <?php foreach ($c['values'] as $v) { ?>
                <tr data-status="<?php echo attr($v['status']); ?>">
                    <td class="uc-code"><?php echo text($v['value'] ?? 'NULL'); ?></td>
                    <td><?php echo text($v['option'] ?? ''); ?></td>
                    <td><?php echo text((string)$v['rows']); ?></td>
                    <td class="uc-code"><?php echo text($v['code'] ?? '—'); ?></td>
                    <td><?php echo text($statusText[$v['status']] ?? $v['status']); ?><?php if ($v['why'] !== '') { ?><div class="text-muted"><?php echo text($v['why']); ?></div><?php } ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        <?php } ?>
    <?php } ?>
</div>
</body>
</html>
