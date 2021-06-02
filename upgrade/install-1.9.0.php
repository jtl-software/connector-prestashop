<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_9_0($object)
{
    $link = \Db::getInstance()->getLink();
    
    if ($link instanceof \PDO) {
        $link->beginTransaction();
    } elseif ($link instanceof \mysqli) {
        $link->begin_transaction();
    }
    
    try {
        $queryInt = 'CREATE TABLE IF NOT EXISTS %s (
                endpoint_id INT(10) NOT NULL,
                host_id INT(10) NOT NULL,
                PRIMARY KEY (endpoint_id),
                INDEX (host_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci';

        $return = $link->query(sprintf($queryInt, 'jtl_connector_link_tax_class'))->execute();

        \Db::getInstance()->getLink()->commit();
        
        return $return;
    } catch (\Exception $e) {
        $link->rollback();
        
        throw $e;
    }
}
