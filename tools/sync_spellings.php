<?php

/**
 * Copies the spelling tables from OpenEMR's UcumCode (the shared unit layer proposed for
 * openemr/openemr#14698) into src/Units/Spellings.php, so that this module and that layer read
 * every unit string the same way. With an OpenEMR tree that has core's UCUM engine
 * (OpenEMR\Cqm\Cql\Ucum\Ucum, master since 2026-10-11), it also checks every code in the tables
 * and in the module's list of common clinical codes against the engine, and records the ones that
 * pass as VERIFIED, which is what the module trusts on an OpenEMR without the engine.
 *
 *     php tools/sync_spellings.php <UcumCode.php> [<OpenEMR tree with the engine>]
 *     php tools/sync_spellings.php --check <UcumCode.php>     exit 1 if the copied tables differ
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$args = array_values(array_slice($argv, 1));
$check = ($args[0] ?? '') === '--check';
if ($check) {
    array_shift($args);
}
$source = $args[0] ?? '';
$openemr = $args[1] ?? '';
if (!is_file($source)) {
    fwrite(STDERR, "usage: php tools/sync_spellings.php [--check] <UcumCode.php> [<OpenEMR tree>]\n");
    exit(2);
}

/** A `private const NAME = [ ... ];` block from the source, as written. */
function constBlock(string $php, string $name): string
{
    if (!preg_match('/^    private const ' . $name . ' = \[\n.*?^    \];\n/ms', $php, $m)) {
        fwrite(STDERR, "$name not found in the source; its shape changed\n");
        exit(2);
    }
    return str_replace('    private const ', '    public const ', $m[0]);
}

$php = (string)file_get_contents($source);
$readAs = constBlock($php, 'READ_AS');
$spellings = constBlock($php, 'SPELLINGS');
$target = dirname(__DIR__) . '/src/Units/Spellings.php';
$current = (string)file_get_contents($target);
$begin = "    // BEGIN copied from OpenEMR\\Common\\Units\\UcumCode by tools/sync_spellings.php; do not edit by hand\n";
$end = "    // END copied\n";
$from = strpos($current, $begin);
$to = strpos($current, $end);
if ($from === false || $to === false) {
    fwrite(STDERR, "the copy markers are missing from src/Units/Spellings.php\n");
    exit(2);
}
$copied = $begin . $readAs . "\n" . $spellings . $end;
$updated = substr($current, 0, $from) . $copied . substr($current, $to + strlen($end));

if ($openemr !== '') {
    require $openemr . '/vendor/autoload.php';
    $engine = \OpenEMR\Cqm\Cql\Ucum\Ucum::instance();
    require_once dirname(__DIR__) . '/src/Units/Spellings.php';
    eval('?>' . str_replace('final class Spellings', 'final class SpellingsNew', $updated));
    $codes = array_values(array_unique(array_merge(
        array_values(\OpenHealthcare\UnitsCheck\Units\SpellingsNew::READ_AS),
        array_values(\OpenHealthcare\UnitsCheck\Units\SpellingsNew::SPELLINGS),
        \OpenHealthcare\UnitsCheck\Units\Spellings::COMMON
    )));
    sort($codes);
    $failed = array_values(array_filter($codes, static fn(string $c): bool => !$engine->isValid($c)));
    if ($failed !== []) {
        fwrite(STDERR, 'not valid UCUM by the engine: ' . implode(', ', $failed) . "\n");
        exit(1);
    }
    $list = "    public const VERIFIED = [\n" . implode('', array_map(static fn(string $c): string => '        ' . var_export($c, true) . ",\n", $codes)) . "    ];\n";
    $updated = (string)preg_replace('/^    public const VERIFIED = \[\n.*?^    \];\n/ms', str_replace('\\', '\\\\', $list), $updated, 1);
    echo count($codes) . " codes checked by the engine, all valid\n";
}

if ($check) {
    if (substr($current, (int)$from, strlen($copied)) !== $copied) {
        fwrite(STDERR, "src/Units/Spellings.php differs from $source; run tools/sync_spellings.php\n");
        exit(1);
    }
    echo "the copied tables match $source\n";
    exit(0);
}
file_put_contents($target, $updated);
echo "wrote src/Units/Spellings.php\n";
