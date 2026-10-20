<?php

declare(strict_types=1);

namespace Tests\Support\Controller;

use Db;
use Feature;
use jtl\Connector\Presta\Controller\SpecificController;
use Psr\Log\NullLogger;

/**
 * Fixture class that extends SpecificController directly without overriding
 * getPrestaSpecificI18ns or getPrestaSpecificValueI18ns, so the real
 * SQL-query implementations can be exercised with a mocked Db.
 */
final class SpecificControllerWithRealQueries extends SpecificController
{
    public function __construct()
    {
        $this->db             = new Db();
        $this->logger         = new NullLogger();
        $this->controllerName = 'SpecificController';
    }

    public function injectDb(Db $db): void
    {
        $this->db = $db;
    }

    protected function getJtlLanguageIsoFromLanguageId(string|int $langId): string
    {
        return 'eng';
    }

    protected function getPrestaLanguageIdFromIso(string $languageIso): int
    {
        return 1;
    }

    public function exposeGetPrestaSpecificI18ns(Feature $feature): array
    {
        return $this->getPrestaSpecificI18ns($feature);
    }

    public function exposeGetPrestaSpecificValueI18ns(int $id): array
    {
        return $this->getPrestaSpecificValueI18ns($id);
    }
}
