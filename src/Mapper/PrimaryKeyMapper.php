<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Mapper;

use Jtl\Connector\Core\Mapper\PrimaryKeyMapperInterface;
use Jtl\Connector\Core\Definition\IdentityType;
use jtl\Connector\Presta\Utils\QueryBuilder;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class PrimaryKeyMapper implements PrimaryKeyMapperInterface, LoggerAwareInterface
{
    /** @var array|string[] */
    protected array $tableNames = [
            'jtl_connector_link_category',
            'jtl_connector_link_crossselling',
            'jtl_connector_link_crossselling_group',
            'jtl_connector_link_customer',
            'jtl_connector_link_image',
            'jtl_connector_link_manufacturer',
            'jtl_connector_link_customer_order',
            'jtl_connector_link_payment',
            'jtl_connector_link_product',
            'jtl_connector_link_specific',
            'jtl_connector_link_specific_value',
            'jtl_connector_link_tax_class'
    ];

    protected \Db $db;

    protected LoggerInterface $logger;

    /**
     * PrimaryKeyMapper constructor.
     */
    public function __construct()
    {
        $this->db     = \Db::getInstance();
        $this->logger = new NullLogger();
    }

    /**
     * @param LoggerInterface $logger
     * @return void
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param int    $type
     * @param string $endpointId
     * @return int|null
     */
    public function getHostId(int $type, string $endpointId): ?int
    {
        $tableName = self::getTableName($type);

        $hostId = null;

        if (!\is_null($tableName)) {
            $queryBuilder = new QueryBuilder();
            $queryBuilder->setUsePrefix(false);
            $sql = $queryBuilder
                ->select('host_id')
                ->from($tableName)
                ->where("endpoint_id='" . \pSQL($endpointId) . "'");

            $dbResult = $this->db->getValue($sql);

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

        return (int)$hostId;
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

        $queryBuilder = new QueryBuilder();
        $queryBuilder->setUsePrefix(false);

        $sql = $queryBuilder
            ->select('endpoint_id')
            ->from($tableName)
            ->where("host_id=$hostId");

        $dbResult = $this->db->getValue($sql);

        $endpointId = (string)$dbResult ?: null;

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
     * @param int    $type
     * @param string $endpointId
     * @param int    $hostId
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
                'INSERT IGNORE INTO %s (endpoint_id, host_id) VALUES ("%s",%d)',
                $tableName,
                \pSQL($endpointId),
                $hostId
            )
        );
    }

    /**
     * @param int         $type
     * @param string|null $endpointId
     * @param int|null    $hostId
     * @return bool
     */
    public function delete(int $type, ?string $endpointId = null, ?int $hostId = null): bool
    {
        $tableName = self::getTableName($type);

        if (\is_null($tableName)) {
            return false;
        }

        $this->logger->debug(
            \sprintf('Delete link with endpointId (%s), hostId (%s) and type (%s)', $endpointId, $hostId, $type)
        );

        $where = [];

        if (!empty($endpointId)) {
            $where[] = 'endpoint_id = "' . \pSQL($endpointId) . '"';
        }

        if ($hostId) {
            $where[] = 'host_id = ' . $hostId;
        }

        if ($where === []) {
            // without an endpoint or host id the statement would delete every link of the table
            $this->logger->warning(
                \sprintf('Refusing to delete links of type (%s) without endpoint or host id', $type)
            );

            return false;
        }

        $queryBuilder = new QueryBuilder();
        $queryBuilder->setUsePrefix(false);

        $sql = $queryBuilder
            ->type('DELETE')
            ->from($tableName)
            ->where(\implode(' AND ', $where));

        return $this->db->execute($sql);
    }

    /**
     * Clears the links of one identity type, or of all types when $type is null. Several identity types share a
     * table (e.g. all image types, customers and customer groups); clearing one of them clears the whole table.
     *
     * @param int|null $type
     * @return bool false for an identity type that has no link table
     */
    public function clear(?int $type = null): bool
    {
        if ($type === null) {
            $tableNames = $this->tableNames;
        } else {
            $tableName = self::getTableName($type);
            if ($tableName === null) {
                return false;
            }
            $tableNames = [$tableName];
        }

        $this->logger->debug(\sprintf('Clearing linking tables: %s', \implode(', ', $tableNames)));

        foreach ($tableNames as $tableName) {
            $this->db->execute('TRUNCATE TABLE ' . $tableName);
        }

        return true;
    }


    /**
     * @param  int $type
     * @return string|null
     */
    public static function getTableName(int $type): ?string
    {
        return match ($type) {
            IdentityType::CATEGORY =>
                'jtl_connector_link_category',
            IdentityType::CROSS_SELLING =>
                'jtl_connector_link_crossselling',
            IdentityType::CROSS_SELLING_GROUP =>
                'jtl_connector_link_crossselling_group',
            IdentityType::CUSTOMER,
            IdentityType::CUSTOMER_GROUP =>
                'jtl_connector_link_customer',
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
                'jtl_connector_link_customer_order',
            IdentityType::PAYMENT =>
                'jtl_connector_link_payment',
            IdentityType::PRODUCT =>
                'jtl_connector_link_product',
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
