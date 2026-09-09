<?php

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
 */
function upgrade_module_3_0_0($object)
{
    foreach (['AdminJtlconnector', 'jtlconnector'] as $tabClassName) {
        $id_tab = (int)Tab::getIdFromClassName($tabClassName);
        if ($id_tab) {
            $tab = new Tab($id_tab);
            $tab->delete();
        }
    }

    $tab            = new Tab();
    $tab->id_parent = (int)Tab::getIdFromClassName('IMPROVE');
    foreach (Language::getLanguages(true) as $lang) {
        if (\is_array($lang) && isset($lang['id_lang']) && \is_numeric($lang['id_lang'])) {
            $tab->name[(int)$lang['id_lang']] = 'JTL-Connector';
        }
    }
    $tab->active     = true;
    $tab->position   = 0;
    $tab->module     = $object->name;
    $tab->class_name = 'AdminJtlconnector';

    return $tab->save();
}
