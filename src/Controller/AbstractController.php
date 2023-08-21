<?php

namespace jtl\Connector\Presta\Controller;

use DI\Container;
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

    protected function getPrestaLanguageIdFromIso(string $languageIso): int
    {
        $sql = (new QueryBuilder())
            ->select('id_lang')
            ->from('lang')
            ->where("iso_code = '$languageIso'");

        return $this->db->executeS($sql)[0]['id_lang'];
    }
}
