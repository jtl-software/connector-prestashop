<?php

//phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Enables the customer push in an existing features file. A features file created on install
 * (from config/features.example.json) already allows it.
 *
 * @param Module $object
 * @return bool
 */
function upgrade_module_1_11_0($object)
{
    $featuresFile = $object->getLocalPath() . 'config/features.json';
    $features     = is_file($featuresFile) ? json_decode((string)file_get_contents($featuresFile), true) : null;
    if (!is_array($features)) {
        return true;
    }
    $features['entities']['Customer']['push'] = true;

    return file_put_contents($featuresFile, json_encode($features, JSON_PRETTY_PRINT)) !== false;
}
