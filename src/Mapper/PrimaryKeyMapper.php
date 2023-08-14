<?php

namespace jtl\Connector\Presta\Mapper;

use Jtl\Connector\Core\Mapper\PrimaryKeyMapperInterface;
use Jtl\Connector\Core\Definition\IdentityType;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class PrimaryKeyMapper implements PrimaryKeyMapperInterface
{
    /**
     * @var array|string[]
     */
    protected array $tableNames = [
            'jtl_connector_link_category',
            'jtl_connector_link_crossselling',
            'jtl_connector_link_crossselling_group',
            'jtl_connector_link_customer',
            'jtl_connector_link_customer_group',
            'jtl_connector_link_image',
            'jtl_connector_link_manufacturer',
            'jtl_connector_link_order',
            'jtl_connector_link_payment',
            'jtl_connector_link_product',
            'jtl_connector_link_shipping_class',
            'jtl_connector_link_specific',
            'jtl_connector_link_specific_value',
            'jtl_connector_link_tax_class'
    ];

    /**
     * @var \Db
     */
    protected \Db $db;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    public function __construct()
    {
        $this->db     = \Db::getInstance();
        $this->logger = new NullLogger();
    }

    /**
     * @param int $type
     * @param string $endpointId
     * @return int|null
     */
    public function getHostId(int $type, string $endpointId): ?int
    {
        $tableName = self::getTableName($type);

        $hostId = null;

        if (!\is_null($tableName)) {
            $dbResult = $this->db->getValue(
                'SELECT host_id FROM jtl_connector_link_' . $tableName . " 
                WHERE endpoint_id = '" . $endpointId . "'"
            );

            $hostId = $dbResult ?: null;

            $this->logger->debug(
                \sprintf(
                    'Trying to get hostId with endpointId (%s) and type (%s) ... hostId: (%s)',
                    $endpointId,
                    $type,
                    $hostId
                )
            );
        }

        return $hostId !== false ? (int)$hostId : null;
    }

    /**
     * @param int $type
     * @param int $hostId
     * @return string|null
     */
    public function getEndpointId(int $type, int $hostId): ?string
    {
        $tableName = self::getTableName($type);

        if (\is_null($tableName)) {
            return null;
        }

        $relation = '';

        $dbResult = $this->db->getValue(
            \sprintf(
                'SELECT endpoint_id FROM jtl_connector_link_%s WHERE host_id = %s%s',
                $tableName,
                $hostId,
                $relation
            )
        );

        $endpointId = $dbResult ?: null;

        $this->logger->debug(
            \sprintf(
                'Trying to get endpointId with hostId (%s) and type (%s) ... endpointId: (%s)',
                $hostId,
                $type,
                $endpointId
            ),
        );

        return $endpointId;
    }

    /**
     * @param int $type
     * @param string $endpointId
     * @param int $hostId
     * @return bool
     */
    public function save(int $type, string $endpointId, int $hostId): bool
    {
        $tableName = self::getTableName($type);

        if (\is_null($tableName)) {
            return false;
        }

        $this->logger->debug(
            \sprintf('Save link with endpointId (%s), hostId (%s) and type (%s)', $endpointId, $hostId, $type)
        );

        return $this->db->execute(
            \sprintf(
                'INSERT IGNORE INTO jtl_connector_link_%s (endpoint_id, host_id) VALUES ("%s",%s)',
                $tableName,
                $endpointId,
                $hostId
            )
        );
    }

    /**
     * @param int $type
     * @param string|null $endpointId
     * @param int|null $hostId
     * @return bool
     */
    public function delete(int $type, string $endpointId = null, int $hostId = null): bool
    {
        $tableName = self::getTableName($type);

        if (\is_null($tableName)) {
            return false;
        }

        $this->logger->debug(
            \sprintf('Delete link with endpointId (%s), hostId (%s) and type (%s)', $endpointId, $hostId, $type)
        );

        $where = [];

        if ($endpointId && $endpointId != '') {
            $where[] = 'endpoint_id = "' . $endpointId . '"';
        }

        if ($hostId) {
            $where[] = 'host_id = ' . $hostId;
        }

        return $this->db->execute(
            \sprintf('DELETE FROM jtl_connector_link_%s WHERE %s', $tableName, \implode(' AND ', $where))
        );
    }

    /**
     * @param int|null $type
     * @return bool
     */
    public function clear(int $type = null): bool
    {
        $this->logger->debug('Clearing linking tables');

        foreach ($this->tableNames as $type) {
            $this->db->execute('TRUNCATE TABLE ' . $type);
        }

        return true;
    }


    /**
     * @param $type
     * @return string|null
     */
    public static function getTableName($type): ?string
    {
        return match ($type) {
            IdentityType::CATEGORY =>
                'jtl_connector_link_category',
            IdentityType::CROSS_SELLING =>
                'jtl_connector_link_crossselling',
            IdentityType::CROSS_SELLING_GROUP =>
                'jtl_connector_link_crossselling_group',
            IdentityType::CUSTOMER =>
                'jtl_connector_link_customer',
            IdentityType::CUSTOMER_GROUP =>
                'jtl_connector_link_customer_group',
            IdentityType::CONFIG_GROUP_IMAGE,
            IdentityType::PRODUCT_VARIATION_VALUE_IMAGE,
            IdentityType::SPECIFIC_IMAGE,
            IdentityType::SPECIFIC_VALUE_IMAGE,
            IdentityType::MANUFACTURER_IMAGE,
            IdentityType::CATEGORY_IMAGE,
            IdentityType::PRODUCT_IMAGE =>
                'jtl_connector_link_image',
            IdentityType::MANUFACTURER =>
                'jtl_connector_link_manufacturer',
            IdentityType::CUSTOMER_ORDER =>
                'jtl_connector_link_order',
            IdentityType::PAYMENT =>
                'jtl_connector_link_payment',
            IdentityType::PRODUCT =>
                'jtl_connector_link_product',
            IdentityType::SHIPPING_CLASS =>
                'jtl_connector_link_shipping_class',
            IdentityType::SPECIFIC =>
                'jtl_connector_link_specific',
            IdentityType::SPECIFIC_VALUE =>
                'jtl_connector_link_specific_value',
            IdentityType::TAX_CLASS =>
                'jtl_connector_link_tax_class',
            default =>
                null,
        };
    }
}
