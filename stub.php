<?php
if (file_exists(__DIR__.'/include.php')) {
    include(__DIR__.'/include.php');
}

Phar::mapPhar('jtlconnector.php');

defined('CONNECTOR_DIR') || define('CONNECTOR_DIR', __DIR__);

include_once 'phar://jtlconnector.php/jtlconnector.php';

__HALT_COMPILER();
