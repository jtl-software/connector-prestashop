<?php

//phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Moves the legacy logs/ dir into var/log. Only install() did that so far, so shops upgraded in place still have it,
 * and since the release ZIP no longer ships logs/ (it protects var/ instead), it is not protected against web access.
 *
 * The move is best effort: a file that cannot be moved must not block the module upgrade.
 *
 * @param Module $object
 * @return bool
 */
function upgrade_module_3_0_0($object)
{
    require_once $object->getLocalPath() . 'src/Utils/LegacyLogDir.php';
    \jtl\Connector\Presta\Utils\LegacyLogDir::migrate($object->getLocalPath());

    return true;
}
