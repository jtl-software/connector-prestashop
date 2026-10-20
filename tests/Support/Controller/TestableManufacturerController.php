<?php

declare(strict_types=1);

namespace Tests\Support\Controller;

use Db;
use Jtl\Connector\Core\Model\Manufacturer as JtlManufacturer;
use Jtl\Connector\Core\Model\ManufacturerI18n as JtlManufacturerI18n;
use jtl\Connector\Presta\Controller\ManufacturerController;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use Language;
use Manufacturer;
use Psr\Log\NullLogger;

/**
 * Testable subclass that:
 * - overrides the constructor to avoid Db::getInstance()
 * - stubs DB-dependent language methods
 * - stubs createJtlManufacturerI18ns to avoid language queries
 * - exposes protected methods as public wrappers
 */
final class TestableManufacturerController extends ManufacturerController
{
    /** @var array<int, JtlManufacturerI18n> */
    private array $stubbedI18ns = [];

    public function __construct()
    {
        $this->db             = new Db();
        $this->logger         = new NullLogger();
        $this->controllerName = 'ManufacturerController';
    }

    /** Inject i18ns that createJtlManufacturerI18ns will return without DB. */
    public function stubI18ns(JtlManufacturerI18n ...$i18ns): void
    {
        $this->stubbedI18ns = $i18ns;
    }

    protected function getPrestaLanguageIdFromIso(string $languageIso): int
    {
        return match ($languageIso) {
            'deu', 'ger' => 2,
            default      => 1,
        };
    }

    protected function createJtlManufacturerI18ns(Manufacturer $manufacturer): array
    {
        return $this->stubbedI18ns;
    }

    public function exposeCreateJtlManufacturers(Manufacturer $presta): JtlManufacturer
    {
        return $this->createJtlManufacturers($presta);
    }

    public function exposeCreatePrestaManufacturer(JtlManufacturer $jtl, Manufacturer $presta): Manufacturer
    {
        return $this->createPrestaManufacturer($jtl, $presta);
    }

    public function exposeCreatePrestaManufacturerTranslations(JtlManufacturerI18n ...$i18ns): array
    {
        return $this->createPrestaManufacturerTranslations(...$i18ns);
    }

    public function injectDb(Db $db): void
    {
        $this->db = $db;
    }

    public function injectMapper(PrimaryKeyMapper $mapper): void
    {
        $this->mapper = $mapper;
    }
}
