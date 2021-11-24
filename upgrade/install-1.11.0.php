<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_11_0($object)
{
    Application()->updateFeaturesFile([
        'entities' => [
            'Customer' => [
                'push' => true
            ]
        ]
    ]);
}
