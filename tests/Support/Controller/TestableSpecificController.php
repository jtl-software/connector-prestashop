<?php

declare(strict_types=1);

namespace Tests\Support\Controller;

use Db;
use Feature;
use FeatureValue;
use jtl\Connector\Presta\Controller\SpecificController;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use Jtl\Connector\Core\Model\Specific;
use Jtl\Connector\Core\Model\SpecificI18n as JtlSpecificI18n;
use Jtl\Connector\Core\Model\SpecificValue as JtlSpecificValue;
use Jtl\Connector\Core\Model\SpecificValueI18n as JtlSpecificValueI18n;
use Psr\Log\NullLogger;

final class TestableSpecificController extends SpecificController
{
    /** @var array<int, array<string, string>> */
    private array $mockSpecificValueI18ns = [];

    /** @var array<int, array<string, string>> */
    private array $mockSpecificI18ns = [];

    public function __construct()
    {
        $this->db             = new Db();
        $this->logger         = new NullLogger();
        $this->controllerName = 'SpecificController';
    }

    protected function getJtlLanguageIsoFromLanguageId(string|int $langId): string
    {
        return 'eng';
    }

    protected function getPrestaLanguageIdFromIso(string $languageIso): int
    {
        return 1;
    }

    public function setMockSpecificValueI18ns(array $i18ns): void
    {
        $this->mockSpecificValueI18ns = $i18ns;
    }

    public function setMockSpecificI18ns(array $i18ns): void
    {
        $this->mockSpecificI18ns = $i18ns;
    }

    protected function getPrestaSpecificValueI18ns(int $id): array
    {
        return $this->mockSpecificValueI18ns;
    }

    protected function getPrestaSpecificI18ns(Feature $prestaSpecific): array
    {
        return $this->mockSpecificI18ns;
    }

    public function exposeCreateJtlSpecificValues(array $data): array
    {
        return $this->createJtlSpecificValues($data);
    }

    public function exposeCreateJtlSpecificValuesI18ns(array $data): array
    {
        return $this->createJtlSpecificValuesI18ns($data);
    }

    public function exposeCreateJtlSpecificI18ns(Feature $feature): array
    {
        return $this->createJtlSpecificI18ns($feature);
    }

    public function exposeCreatePrestaSpecificI18ns(JtlSpecificI18n ...$i18ns): array
    {
        return $this->createPrestaSpecificI18ns(...$i18ns);
    }

    public function exposeCreatePrestaSpecificValues(
        FeatureValue $prestaValue,
        JtlSpecificValue $jtlValue,
        string $prestaSpecificId
    ): FeatureValue {
        return $this->createPrestaSpecificValues($prestaValue, $jtlValue, $prestaSpecificId);
    }

    public function injectDb(Db $db): void
    {
        $this->db = $db;
    }

    public function injectMapper(PrimaryKeyMapper $mapper): void
    {
        $this->mapper = $mapper;
    }

    public function exposeCreateJtlSpecific(Feature $feature): Specific
    {
        return $this->createJtlSpecific($feature);
    }

    public function exposeCreatePrestaSpecific(
        Specific $jtlSpecific,
        Feature $prestaSpecific
    ): Feature {
        return $this->createPrestaSpecific($jtlSpecific, $prestaSpecific);
    }

    public function exposeCreatePrestaSpecificValueI18ns(
        JtlSpecificValueI18n $i18n,
        FeatureValue $prestaSpecificValue
    ): FeatureValue {
        return $this->createPrestaSpecificValueI18ns($i18n, $prestaSpecificValue);
    }
}
