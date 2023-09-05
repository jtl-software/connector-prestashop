<?php

namespace jtl\Connector\Presta\Controller;

use DI\Container;
use Jtl\Connector\Core\Model\QueryFilter;
use jtl\Connector\Presta\Utils\QueryBuilder;
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
     * @var string|null
     */
    protected ?string $controllerName;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

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

    public function __construct()
    {
        $this->db = \Db::getInstance();

        $reflect              = new \ReflectionClass($this);
        $this->controllerName = $reflect->getShortName();
        $this->logger         = new NullLogger();
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
            ->select('iso_code')
            ->from('lang')
            ->where("id_lang = $langId");

        $result = $this->db->executeS($sql);

        $linguaConverter = Service::createFromISO_639_1($result[0]['iso_code']);

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

        $result = $this->db->executeS($sql)[0]['id_lang'];

        if (\is_null($result)) {
            throw new \RuntimeException("Language '$languageIso' is missing in Prestashop");
        }

        return $this->db->executeS($sql)[0]['id_lang'];
    }

    /**
     * @param string $languageIso
     * @return int
     * @throws PrestaShopDatabaseException
     */
    protected function getPrestaCountryIdFromIso(string $languageIso): int
    {
        $sql = (new QueryBuilder())
            ->select('id_country')
            ->from('country')
            ->where("iso_code = '$languageIso'");

        return $this->db->executeS($sql)[0]['id_country'];
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

        return $this->db->executeS($sql)[0]['iso_code'];
    }

    /**
     * @param QueryFilter $queryFilter
     * @param string $linkingTable
     * @param string $prestaTable
     * @param string $columns
     * @param string|null $fromDate
     * @return array
     * @throws PrestaShopDatabaseException
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
            ->limit($this->db->escape($queryFilter->getLimit()));

        return $this->db->executeS($sql);
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
}
