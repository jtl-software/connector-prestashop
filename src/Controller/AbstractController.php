<?php

namespace jtl\Connector\Presta\Controller;

use DI\Container;
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

    protected function getPrestaLanguage(int $langId): string
    {
        $result = $this->db->executeS(
            'SELECT iso_code
            FROM ' . \_DB_PREFIX_ . 'lang
            WHERE id_lang = ' . $langId
        );

        $linguaConverter = Service::createFromISO_639_1($result[0]['iso_code']);

        return $linguaConverter->toISO_639_2b();
    }
}
