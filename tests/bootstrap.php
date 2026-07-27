<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap file.
 *
 * Loads the Composer autoloader (Composer's vendor-dir is `lib/` in this project)
 * and defines the runtime constants the module usually receives from PrestaShop
 * so that the classes under test can be loaded in isolation.
 */

$autoload = __DIR__ . '/../lib/autoload.php';

if (!\is_file($autoload)) {
    \fwrite(\STDERR, "Composer dependencies are not installed. Run 'composer install' first.\n");
    exit(1);
}

require $autoload;

if (!\defined('CONNECTOR_DIR')) {
    $connectorDir = \sys_get_temp_dir() . '/jtlconnector-tests';

    if (!\is_dir($connectorDir . '/config')) {
        \mkdir($connectorDir . '/config', 0777, true);
    }

    \define('CONNECTOR_DIR', $connectorDir);
}
