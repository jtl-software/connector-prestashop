<?php

//phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Moves the legacy logs/ dir into var/log. Only install() did that so far, so shops upgraded in place still have it,
 * and since the release ZIP no longer ships logs/ (it protects var/ instead), it is not protected against web access.
 *
 * Removes the broken manufacturer link that 2.x saved: it linked a new manufacturer with endpoint and host id swapped,
 * i.e. with an empty endpoint, which the INT column stores as 0 (CO-3735). Such a row can hide the real link of its
 * host id, and the next sync would add that manufacturer again.
 *
 * Both are best effort: they must not block the module upgrade.
 *
 * @param Module $object
 * @return bool
 */
function upgrade_module_3_0_0($object)
{
    require_once $object->getLocalPath() . 'src/Utils/LegacyLogDir.php';
    \jtl\Connector\Presta\Utils\LegacyLogDir::migrate($object->getLocalPath());

    try {
        Db::getInstance()->execute('DELETE FROM `jtl_connector_link_manufacturer` WHERE `endpoint_id` = 0');
    } catch (\Exception $e) {
        // best effort, see above
    }

    return true;
}
