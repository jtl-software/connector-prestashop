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
 * id and position are kept. PrestaShop stores the profile permissions of a tab
 * in authorization_role rows named after the tab's class name
 * (ROLE_MOD_TAB_<CLASSNAME>_<ACTION>) and Tab::update() does not touch them, so
 * those rows are renamed as well: the existing grants (ps_access) then carry
 * over to the new class name.
 *
 * @param object $object the module instance (a \Module)
 * @return bool
 */
function upgrade_module_3_0_0(object $object): bool
{
    $legacyTabId = (int)Tab::getIdFromClassName('jtlconnector');

    $migratedTabId = (int)Tab::getIdFromClassName('AdminJtlconnector');
    if ($migratedTabId > 0) {
        // Already migrated, possibly by an interrupted earlier run: finish the permission roles and drop a
        // leftover legacy tab (PS9 rejects tabs without a matching controller file). The roles must be
        // renamed first because Tab::delete() removes the roles named after the deleted tab. All steps are
        // safe to repeat.
        if (!upgrade_module_3_0_0_rename_roles()) {
            return false;
        }

        if ($legacyTabId > 0 && !(new Tab($legacyTabId))->delete()) {
            return false;
        }

        return Tab::initAccess($migratedTabId) !== false;
    }

    if ($legacyTabId > 0) {
        $tab             = new Tab($legacyTabId);
        $tab->class_name = 'AdminJtlconnector';

        if (!$tab->save()) {
            return false;
        }

        if (!upgrade_module_3_0_0_rename_roles()) {
            return false;
        }

        // creates any role that is still missing and grants the current profile
        return Tab::initAccess($legacyTabId) !== false;
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

/**
 * Renames ROLE_MOD_TAB_JTLCONNECTOR_<ACTION> to ROLE_MOD_TAB_ADMINJTLCONNECTOR_<ACTION>
 * so the profile permissions of the legacy tab are kept. A role that already exists
 * under the new name is left alone (the slug column is unique).
 *
 * @return bool false if a statement failed
 */
function upgrade_module_3_0_0_rename_roles(): bool
{
    $db     = Db::getInstance();
    $table  = _DB_PREFIX_ . 'authorization_role';
    $result = true;

    foreach (['CREATE', 'READ', 'UPDATE', 'DELETE'] as $action) {
        $old = 'ROLE_MOD_TAB_JTLCONNECTOR_' . $action;
        $new = 'ROLE_MOD_TAB_ADMINJTLCONNECTOR_' . $action;

        $exists = $db->getValue('SELECT `slug` FROM `' . $table . '` WHERE `slug` = "' . pSQL($new) . '"');
        if (!empty($exists)) {
            continue;
        }

        $result = $db->execute(
            'UPDATE `' . $table . '` SET `slug` = "' . pSQL($new) . '" WHERE `slug` = "' . pSQL($old) . '"'
        ) && $result;
    }

    return $result;
}
