<?php

//phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

if (!defined('_PS_VERSION_')) {
    exit;
}

function jtl_connector_migration_hotfix($object)
{
    $db    = \Db::getInstance();
    $query = 'ALTER TABLE `%s` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;';

    $newLinkingTables = $db->executeS('SHOW TABLES LIKE "jtl_connector_link_%"');

    if (!empty($newLinkingTables)) {
        foreach ($newLinkingTables as $newLinkingTable) {
            if (!empty($newLinkingTable)) {
                $newLinkingTable = reset($newLinkingTable);
                if ($newLinkingTable !== 'jtl_connector_link_backup') {
                    $db->query(sprintf($query, $newLinkingTable))->execute();
                }
            }
        }
    }

    return true;
}

function upgrade_module_1_6_2($object)
{
    $link = \Db::getInstance()->getLink();

    if ($link instanceof \PDO) {
        $link->beginTransaction();
    } elseif ($link instanceof \mysqli) {
        $link->begin_transaction();
    }

    try {
        $return = jtl_connector_migration_hotfix($object);
        \Db::getInstance()->getLink()->commit();

        return $return;
    } catch (\Exception $e) {
        $link->rollback();

        throw $e;
    }
}
