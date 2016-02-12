<?php
Phar::mapPhar('connector.phar');
Phar::interceptFileFuncs();

include_once 'phar://connector.phar/vendor/autoload.php';

__HALT_COMPILER();
