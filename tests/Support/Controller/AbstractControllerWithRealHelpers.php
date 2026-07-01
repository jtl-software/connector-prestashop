<?php

declare(strict_types=1);

namespace Tests\Support\Controller;

use Db;
use jtl\Connector\Presta\Controller\AbstractController;
use Jtl\Connector\Core\Model\QueryFilter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Subclass that does NOT override any helper methods, so the real
 * AbstractController code paths are exercised.
 */
final class AbstractControllerWithRealHelpers extends AbstractController
{
    public function __construct(Db $db)
    {
        $this->db             = $db;
        $this->logger         = new NullLogger();
        $this->controllerName = 'ConcreteController';
    }

    public function exposeSetLogger(LoggerInterface $logger): void
    {
        $this->setLogger($logger);
    }

    public function exposeGetJtlLang(string|int $id): string
    {
        return $this->getJtlLanguageIsoFromLanguageId($id);
    }

    public function exposeGetPrestaLangId(string $iso): int
    {
        return $this->getPrestaLanguageIdFromIso($iso);
    }

    public function exposeGetPrestaCountryId(string $iso): ?int
    {
        return $this->getPrestaCountryIdFromIso($iso);
    }

    public function exposeGetDefaultCountryId(): int
    {
        return $this->getDefaultPrestaShopCountryId();
    }

    public function exposeGetJtlCountryIso(int|string $id): string
    {
        return $this->getJtlCountryIsoFromPrestaCountryId($id);
    }

    public function exposeGetNotLinked(
        QueryFilter $qf,
        string $lt,
        string $pt,
        string $cols,
        ?string $from = null
    ): array {
        return $this->getNotLinkedEntities($qf, $lt, $pt, $cols, $from);
    }

    public function exposeGetContextLanguageId(): int
    {
        return $this->getPrestaContextLanguageId();
    }

    public function exposeGetContextShopId(): int
    {
        return $this->getPrestaContextShopId();
    }
}
