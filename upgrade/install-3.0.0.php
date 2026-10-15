<?php

declare(strict_types=1);

//phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * PrestaShop 9's LegacyRouterChecker requires a matching admin controller
 * file for any tab registered with a module reference. The module now ships
 * controllers/admin/AdminJtlconnectorController.php and registers the tab
 * under the matching class_name "AdminJtlconnector" (see jtlconnector.php
 * install()); this migrates the tab of an already-installed shop, which
 * would otherwise keep pointing at the old, now-nonexistent "jtlconnector"
 * class_name and get rejected by PS9's admin.
 *
 * An existing tab is renamed in place instead of deleted and recreated, so its
 * id, position and the access rights the merchant granted to admin profiles
 * are kept.
 *
 * @param object $object the module instance
 * @return bool
 */
function upgrade_module_3_0_0(object $object): bool
{
    if ((int)Tab::getIdFromClassName('AdminJtlconnector') > 0) {
        return true;
    }

    $legacyTabId = (int)Tab::getIdFromClassName('jtlconnector');
    if ($legacyTabId > 0) {
        $tab             = new Tab($legacyTabId);
        $tab->class_name = 'AdminJtlconnector';

        return (bool)$tab->save();
    }

    // No tab at all (e.g. removed manually): register it like install() does.
    $parentId = (int)Tab::getIdFromClassName('IMPROVE');
    if ($parentId === 0) {
        return false;
    }

    $tab            = new Tab();
    $tab->id_parent = $parentId;
    foreach (Language::getLanguages(true) as $lang) {
        if (\is_array($lang) && isset($lang['id_lang']) && \is_numeric($lang['id_lang'])) {
            $tab->name[(int)$lang['id_lang']] = 'JTL-Connector';
        }
    }
    $tab->active     = true;
    $tab->position   = 0;
    $tab->module     = $object->name;
    $tab->class_name = 'AdminJtlconnector';

    return (bool)$tab->save();
}
