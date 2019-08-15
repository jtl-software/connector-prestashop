<?php

use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;

require '../../config/config.inc.php';
$loader = require 'vendor/autoload.php';

function getPrimaryKeyMapper()
{
    return new PrimaryKeyMapper();
}
