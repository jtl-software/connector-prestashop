<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Controller;

use DI\Container;
use Jtl\Connector\Core\Model\QueryFilter;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use jtl\Connector\Presta\Utils\QueryBuilder;
use mysqli_result;
use PDOStatement;
use PrestaShopDatabaseException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WhiteCube\Lingua\Service;

abstract class AbstractController implements LoggerAwareInterface
{
    /**
     * @var \Db
     */
    protected \Db $db;

    /**
     * @var Container
     */
    protected Container $container;

    /**
     * @var string
     */
    protected string $controllerName;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    protected PrimaryKeyMapper $mapper;

    protected const
        CATEGORY_LINKING_TABLE       = 'jtl_connector_link_category',
        CUSTOMER_LINKING_TABLE       = 'jtl_connector_link_customer',
        CUSTOMER_ORDER_LINKING_TABLE = 'jtl_connector_link_customer_order',
        DELIVERY_NOTE_LINKING_TABLE  = 'jtl_connector_link_delivery_note',
        IMAGE_LINKING_TABLE          = 'jtl_connector_link_image',
        MANUFACTURER_LINKING_TABLE   = 'jtl_connector_link_manufacturer',
        PAYMENT_LINKING_TABLE        = 'jtl_connector_link_payment',
        PRODUCT_LINKING_TABLE        = 'jtl_connector_link_product',
        SPECIFIC_LINKING_TABLE       = 'jtl_connector_link_specific',
        SPECIFIC_VALUE_LINKING_TABLE = 'jtl_connector_link_specific_value',
        TAX_CLASS_LINKING_TABLE      = 'jtl_connector_link_tax_class';

    /**
     * @param PrimaryKeyMapper $mapper
     */
    public function __construct(PrimaryKeyMapper $mapper)
    {
        $this->db = \Db::getInstance();

        $reflect              = new \ReflectionClass($this);
        $this->controllerName = $reflect->getShortName();
        $this->logger         = new NullLogger();
        $this->mapper         = $mapper;
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
     * @param int $langId
     * @return string
     * @throws PrestaShopDatabaseException
     */
    protected function getJtlLanguageIsoFromLanguageId(int $langId): string
    {
        $sql = (new QueryBuilder())
            ->select('language_code')
            ->from('lang')
            ->where("id_lang = $langId");

        /** @var array{0: array{language_code: string}} $result */
        $result = $this->db->executeS($sql->build());

        $code   = $result[0]['language_code'];
        $code   = \explode('-', $code)[0];

        $linguaConverter = Service::createFromISO_639_1($code);

        return $linguaConverter->toISO_639_2b();
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws \RuntimeException
     */
    protected function getPrestaLanguageIdFromIso(string $languageIso): int
    {
        if (\strlen($languageIso) === 3) {
            $linguaConverter = Service::createFromISO_639_2b($languageIso);
            $languageIso     = $linguaConverter->toISO_639_1();
        }

        $sql = (new QueryBuilder())
            ->select('id_lang')
            ->from('lang')
            ->where("iso_code = '$languageIso'");

        /** @var array{0: array{id_lang: string|null}} $result */
        $result = $this->db->executeS($sql->build());

        if (\is_null($result[0]['id_lang'])) {
            throw new \RuntimeException("Language '$languageIso' is missing in Prestashop");
        }

        return (int)$result[0]['id_lang'];
    }

    /**
     * @param string $languageIso
     * @return int|null
     * @throws PrestaShopDatabaseException
     */
    protected function getPrestaCountryIdFromIso(string $languageIso): ?int
    {
        $sql = (new QueryBuilder())
            ->select('id_country')
            ->from('country')
            ->where("iso_code = '$languageIso'");

        /** @var array{0: array{id_country: string|null}}|array{} $result */
        $result = $this->db->executeS($sql->build());
        if (\count($result) === 0 || !isset($result[0]['id_country'])) {
            return null;
        }
        return (int)$result[0]['id_country'];
    }

    /**
     * @param int $languageId
     * @return string
     * @throws PrestaShopDatabaseException
     */
    protected function getJtlCountryIsoFromPrestaCountryId(int $languageId): string
    {
        $sql = (new QueryBuilder())
            ->select('iso_code')
            ->from('country')
            ->where("id_country = $languageId");

        /** @var array{0: array{iso_code: string}} $result */
        $result = $this->db->executeS($sql->build());
        return $result[0]['iso_code'];
    }


    //TODO: Rewrite to support multiple leftjoins or remove.
    /**
     * @param QueryFilter $queryFilter
     * @param string $linkingTable
     * @param string $prestaTable
     * @param string $columns
     * @param string|null $fromDate
     *
     * @return array<int, array<string, string>>
     * @throws PrestaShopDatabaseException|\PrestaShopException
     */
    protected function getNotLinkedEntities(
        QueryFilter $queryFilter,
        string $linkingTable,
        string $prestaTable,
        string $columns,
        ?string $fromDate = null
    ): array {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->setUsePrefix(false);

        $fromDate = $fromDate !== null ? \str_replace('-', '', $fromDate) : null;

        $where = $fromDate === null ? '' : " AND pt.date_add >= $fromDate";
        $sql   = $queryBuilder
            ->select($columns)
            ->from(\_DB_PREFIX_ . $prestaTable, 'pt')
            ->leftJoin($linkingTable, 'lt', "pt.$columns = lt.endpoint_id")
            ->where('lt.host_id IS NULL' . $where)
            ->limit($queryFilter->getLimit());

        // if order table, check if order has deleted column
        if ($linkingTable === self::CUSTOMER_ORDER_LINKING_TABLE) {
            $sql2   = \sprintf("SHOW COLUMNS FROM `%s%s` LIKE 'deleted';", \_DB_PREFIX_, $prestaTable);
            /** @var array<int, array<string, string>> $result */
            $result = $this->db->executeS($sql2);
            if (\count($result) !== 0) {
                $sql->where('pt.deleted = 0');
            }
        }

        /** @var array<int, array<string, string>> $return */
        $return = $this->db->executeS($sql->build());
        return $return;
    }

    /**
     * @param \Customer $prestaCustomer
     * @return string
     */
    protected function determineSalutation(\Customer $prestaCustomer): string
    {
        $mappings = ['1' => 'm', '2' => 'w'];
        if (isset($mappings[$prestaCustomer->id_gender])) {
            return $mappings[$prestaCustomer->id_gender];
        }

        return '';
    }

    /**
     * @param string|null $date
     * @return \DateTimeInterface|null
     * @throws \Exception
     */
    protected function createDateTime(?string $date): ?\DateTimeInterface
    {
        if ($date === null) {
            return null;
        }

        if ($date === '0000-00-00') {
            return null;
        }

        $object = new \DateTime($date);

        if ($object < new \DateTime('1970-01-01 00:00:00')) {
            return null;
        }

        return $object;
    }
}
