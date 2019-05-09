<?php
namespace jtl\Connector\Presta\Mapper;

use jtl\Connector\Linker\IdentityLinker;
use jtl\Connector\Mapper\IPrimaryKeyMapper;
use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Drawing\ImageRelationType;

class PrimaryKeyMapper implements IPrimaryKeyMapper
{
    protected $db;
    
    protected static $types = array(
        IdentityLinker::TYPE_CATEGORY => 'category',
        IdentityLinker::TYPE_CUSTOMER => 'customer',
        IdentityLinker::TYPE_CUSTOMER_ORDER => 'customer_order',
        IdentityLinker::TYPE_DELIVERY_NOTE => 'delivery_note',
        IdentityLinker::TYPE_IMAGE => 'image',
        IdentityLinker::TYPE_MANUFACTURER => 'manufacturer',
        IdentityLinker::TYPE_PRODUCT => 'product',
        IdentityLinker::TYPE_SPECIFIC => 'specific',
        IdentityLinker::TYPE_SPECIFIC_VALUE => 'specific_value',
        IdentityLinker::TYPE_PAYMENT => 'payment',
        IdentityLinker::TYPE_CROSSSELLING => 'crossselling',
        IdentityLinker::TYPE_CROSSSELLING_GROUP => 'crossselling_group'
    );

    public function __construct()
    {
        $this->db = \Db::getInstance();
    }

    public function getHostId($endpointId, $type)
    {
        if (isset(static::$types[$type])) {
            $dbResult = $this->db->getValue('SELECT host_id FROM jtl_connector_link_' . static::$types[$type] . " WHERE endpoint_id = '" . $endpointId . "'");
    
            $hostId = $dbResult ? $dbResult : null;
    
            Logger::write(sprintf('Trying to get hostId with endpointId (%s) and type (%s) ... hostId: (%s)', $endpointId, $type, $hostId), Logger::DEBUG, 'linker');
    
            return $hostId;
        }
        
        return null;
    }

    public function getEndpointId($hostId, $type, $relationType = null)
    {
        if (isset(static::$types[$type])) {
            return null;
        }
        
        $relation = '';

        if ($type == IdentityLinker::TYPE_IMAGE) {
            switch ($relationType) {
                case ImageRelationType::TYPE_CATEGORY:
                    $relation = ' AND endpointId LIKE "c%"';
                    break;
                case ImageRelationType::TYPE_MANUFACTURER:
                    $relation = ' AND endpointId LIKE "m%"';
                    break;
            }
        }

        $dbResult = $this->db->getValue(sprintf('SELECT endpointId FROM jtl_connector_link_%s WHERE hostId = %s AND type = %s%s', static::$types[$type], $hostId, $type, $relation));

        $endpointId = $dbResult ? $dbResult : null;

        Logger::write(sprintf('Trying to get endpointId with hostId (%s) and type (%s) and relation type (%s) ... endpointId: (%s)', $hostId, $type, $relationType, $endpointId), Logger::DEBUG, 'linker');

        return $endpointId;
    }

    public function save($endpointId, $hostId, $type)
    {
        Logger::write(sprintf('Save link with endpointId (%s), hostId (%s) and type (%s)', $endpointId, $hostId, $type), Logger::DEBUG, 'linker');
        //TODO: endpoint_id NICHT endpointId
        $test2 = sprintf('INSERT IGNORE INTO jtl_connector_link_%s (endpointId, hostId) VALUES ("%s",%s)', static::$types[$type], $endpointId, $hostId);
        $test = $this->db->execute(sprintf('INSERT IGNORE INTO jtl_connector_link_%s (endpointId, hostId) VALUES ("%s",%s)',
            static::$types[$type],
            $endpointId,
            $hostId));
    }

    public function delete($endpointId = null, $hostId = null, $type)
    {
        Logger::write(sprintf('Delete link with endpointId (%s), hostId (%s) and type (%s)', $endpointId, $hostId, $type), Logger::DEBUG, 'linker');

        $where = '';

        if ($endpointId && $endpointId != '') {
            $where .= ' && endpointId = "'.$endpointId.'"';
        }

        if ($hostId) {
            $where .= ' && hostId = '.$hostId;
        }

        $this->db->execute(sprintf('DELETE FROM jtl_connector_link_%s WHERE %s', static::$types[$type], $where));
    }

    public function clear()
    {
        Logger::write('Clearing linking tables', Logger::DEBUG, 'linker');
        
        foreach (static::$types as $id => $name) {
            $this->db->execute('TRUNCATE TABLE jtl_connector_link_' . $name);
        }
    
        return true;
    }

    public function gc()
    {
        return true;
    }
}
