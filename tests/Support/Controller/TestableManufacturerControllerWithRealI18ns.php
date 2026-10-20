<?php

declare(strict_types=1);

namespace Tests\Support\Controller;

use Db;
use jtl\Connector\Presta\Controller\ManufacturerController;
use Language;
use Manufacturer;
use Psr\Log\NullLogger;

/**
 * Extends ManufacturerController with a real createJtlManufacturerI18ns implementation
 * and stubbed language-lookup methods, so the I18n logic can be exercised without a DB.
 */
final class TestableManufacturerControllerWithRealI18ns extends ManufacturerController
{
    public function __construct()
    {
        $this->db             = new Db();
        $this->logger         = new NullLogger();
        $this->controllerName = 'ManufacturerController';
    }

    protected function getPrestaLanguageIdFromIso(string $languageIso): int
    {
        return 1;
    }

    protected function getJtlLanguageIsoFromLanguageId(string|int $langId): string
    {
        return 'eng';
    }

    public function exposeCreateJtlManufacturerI18ns(Manufacturer $manufacturer): array
    {
        return $this->createJtlManufacturerI18ns($manufacturer);
    }
}
