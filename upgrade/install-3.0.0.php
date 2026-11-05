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
 * Migrates the back office menu entry (tab) to the class name "AdminJtlconnector" (CO-3113): PrestaShop 9 only opens
 * a module's menu entry if the module ships an admin controller of that name, and the entry of earlier releases
 * ("jtlconnector") has none. Its result is the upgrade's result; all its steps are safe to repeat.
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

    return upgrade_module_3_0_0_migrate_tab($object);
}

/**
 * Renames the menu entry in place instead of deleting and re-adding it, so it keeps its id and position. PrestaShop
 * stores the profile permissions of a tab in authorization_role rows named after its class name
 * (ROLE_MOD_TAB_<CLASSNAME>_<ACTION>), which Tab::update() leaves alone: they are renamed too, so the existing grants
 * carry over.
 *
 * @param Module $object
 * @return bool
 */
function upgrade_module_3_0_0_migrate_tab($object)
{
    $legacyTabId   = (int)Tab::getIdFromClassName('jtlconnector');
    $migratedTabId = (int)Tab::getIdFromClassName('AdminJtlconnector');

    if ($migratedTabId > 0) {
        // Already migrated, e.g. by an interrupted earlier run: finish the roles and drop a leftover legacy entry.
        // The roles are renamed first, because Tab::delete() removes the roles named after the deleted entry.
        if (!upgrade_module_3_0_0_rename_tab_roles()) {
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
        if (!$tab->save() || !upgrade_module_3_0_0_rename_tab_roles()) {
            return false;
        }

        // adds any role that is still missing and grants the current profile
        return Tab::initAccess($legacyTabId) !== false;
    }

    // no entry at all (e.g. removed by hand): add it as install() does
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
 * Renames ROLE_MOD_TAB_JTLCONNECTOR_<ACTION> to ROLE_MOD_TAB_ADMINJTLCONNECTOR_<ACTION>, so the profile permissions of
 * the legacy entry are kept. A role that already exists under the new name is left alone (the slug is unique).
 *
 * @return bool false if a statement failed
 */
function upgrade_module_3_0_0_rename_tab_roles()
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
