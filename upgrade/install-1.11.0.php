<?php

use jtl\Connector\Application\Application;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once  __DIR__ . '/../lib/autoload.php';

function upgrade_module_1_11_0($object)
{
    Application::getInstance()->updateFeaturesFile([
        'entities' => [
            'Customer' => [
                'push' => true
            ]
        ]
    ]);

    return true;
}
