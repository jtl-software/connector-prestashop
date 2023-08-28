<?php

namespace jtl\Connector\Presta\Controller;

use DI\Container;
use jtl\Connector\Presta\Utils\QueryBuilder;
use PrestaShop\PrestaShop\Core\Foundation\IoC\Exception;
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

    public function __construct()
    {
        $this->db = \Db::getInstance();

        $reflect              = new \ReflectionClass($this);
        $this->controllerName = $reflect->getShortName();
        $this->logger         = new NullLogger();
    }

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
     * @throws Exception
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
            throw new Exception("Language '$languageIso' is missing in Prestashop");
        }

        return $this->db->executeS($sql)[0]['id_lang'];
    }

    protected function getPrestaCountryIdFromIso(string $languageIso): int
    {
        $sql = (new QueryBuilder())
            ->select('id_country')
            ->from('country')
            ->where("iso_code = '$languageIso'");

        return $this->db->executeS($sql)[0]['id_country'];
    }
}
