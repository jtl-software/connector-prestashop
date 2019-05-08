<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function jtl_connector_migration($object)
{
    $db = Db::getInstance();
    
    $types = [
        1    => 'category',
        2    => 'customer',
        4    => 'customer_order',
        8    => 'delivery_note',
        16   => 'image',
        32   => 'manufacturer',
        64   => 'product',
        128  => 'specific',
        256  => 'specific_value',
        512  => 'payment',
        1024 => 'crossselling',
        2048 => 'crossselling_group',
    ];
    
    $queryInt = 'CREATE TABLE IF NOT EXISTS %s (
                endpoint_id INT(10) NOT NULL,
                host_id INT(10) NOT NULL,
                PRIMARY KEY (endpoint_id),
                INDEX (host_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci';
    
    $queryChar = 'CREATE TABLE IF NOT EXISTS %s (
                endpoint_id varchar(255) NOT NULL,
                host_id INT(10) NOT NULL,
                PRIMARY KEY (endpoint_id),
                INDEX (host_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci';
    
    foreach ($types as $id => $name) {
        if ($id == 16 || $id == 64) {
            $db->query(sprintf($queryChar, 'jtl_connector_link_' . $name));
        } else {
            $db->query(sprintf($queryInt, 'jtl_connector_link_' . $name));
        }
    }
    //TODO: EXECUTE ALL QUERIES
    $check = $db->query('SHOW TABLES LIKE "jtl_connector_link"');
    
    if ($check == true) {
        $existingTypes = $db->query('SELECT type FROM jtl_connector_link GROUP BY type');
        
        foreach ($existingTypes as $existingType) {
            $typeId = (int)$existingType['type'];
            $tableName = 'jtl_connector_link_' . $types[$typeId];
            $db->query("INSERT INTO {$tableName} (host_id, endpoint_id)
                        SELECT hostId, endpointId FROM jtl_connector_link WHERE type = {$typeId}
                        ");
        }
        
        if (count($existingTypes) > 0) {
            $db->query("RENAME TABLE jtl_connector_link TO jtl_connector_link_backup");
        }
    }
    
    return true;
}

function upgrade_module_1_5_9($object)
{
    $link = \Db::getInstance()->getLink();
    
    if ($link instanceof \PDO) {
        $link->beginTransaction();
    } elseif ($link instanceof \mysqli) {
        $link->begin_transaction();
    }
    
    try {
        return jtl_connector_migration($object);
    } catch (\Exception $e) {
        $link->rollback();
        
        throw $e;
    }
}
