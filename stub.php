<?php
Phar::mapPhar('connector.phar');
Phar::interceptFileFuncs();

$loader = include_once 'phar://connector.phar/vendor/autoload.php';
$loader->add('', CONNECTOR_DIR . '/plugins');

__HALT_COMPILER();
