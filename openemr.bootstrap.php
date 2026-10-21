<?php

/**
 * Units Check module bootstrap, loaded by OpenEMR on every request while the module is enabled.
 *
 * @package   OpenHealthcare\UnitsCheck
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License 3
 */

use OpenEMR\Core\ModulesClassLoader;
use OpenHealthcare\UnitsCheck\Bootstrap;
use OpenHealthcare\UnitsCheck\Compat\OpenEmr;

// OEGlobalsBag is 8.0+; 7.0 keeps the project directory in $GLOBALS['fileroot'].
$projectDir = class_exists('OpenEMR\\Core\\OEGlobalsBag') ? \OpenEMR\Core\OEGlobalsBag::getInstance()->getProjectDir() : (string)($GLOBALS['fileroot'] ?? '');
$classLoader = new ModulesClassLoader($projectDir);
$classLoader->registerNamespaceIfNotExists('OpenHealthcare\\UnitsCheck\\', __DIR__ . DIRECTORY_SEPARATOR . 'src');

(new Bootstrap(OpenEmr::eventDispatcher()))->subscribeToEvents();
