<?php

use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;

require '../../config/config.inc.php';
$loader = require 'lib/autoload.php';

const TEST_DIR = __DIR__;

function getPrimaryKeyMapper()
{
    return new PrimaryKeyMapper();
}
