<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Carrier;
use Currency;
use Db;
use Group;
use Jtl\Connector\Core\Model\CustomerGroup as JtlCustomerGroup;
use Jtl\Connector\Core\Model\CustomerGroupI18n as JtlCustomerGroupI18n;
use Jtl\Connector\Core\Model\Currency as JtlCurrency;
use Jtl\Connector\Core\Model\GlobalData as JtlGlobalData;
use Jtl\Connector\Core\Model\QueryFilter;
use Jtl\Connector\Core\Model\TaxRate as JtlTaxRate;
use Jtl\Connector\Core\Model\Language as JtlLanguage;
use Jtl\Connector\Core\Model\ShippingMethod as JtlShippingMethod;
use jtl\Connector\Presta\Controller\GlobalDataController;
use Language;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Tax;


final class TestableGlobalDataController extends GlobalDataController
{
    public function __construct()
    {
        $this->db             = new Db();
        $this->logger         = new NullLogger();
        $this->controllerName = 'GlobalDataController';
    }

    protected function getJtlLanguageIsoFromLanguageId(string|int $langId): string
    {
        return 'eng';
    }

    protected function getPrestaContextLanguageId(): int
    {
        return 1;
    }

    public function exposeCreateJtlCustomerGroupI18n(array $prestaCustomerGroup): JtlCustomerGroupI18n
    {
        return $this->createJtlCustomerGroupI18n($prestaCustomerGroup);
    }

    public function injectDb(Db $db): void
    {
        $this->db = $db;
    }

    public function exposeCreateJtlCurrency(): JtlCurrency
    {
        return $this->createJtlCurrency();
    }

    public function exposeGetCustomerGroups(): array
    {
        return $this->getCustomerGroups();
    }

    public function exposeGetTaxRates(): array
    {
        return $this->getTaxRates();
    }

    public function exposeGetLanguages(): array
    {
        return $this->getLanguages();
    }

    public function exposeGetShippingMethods(): array
    {
        return $this->getShippingMethods();
    }
}

final class GlobalDataControllerTest extends TestCase
{
    private TestableGlobalDataController $controller;

    protected function setUp(): void
    {
        Db::resetInstance();
        Currency::resetMock();
        Group::resetMock();
        Tax::resetMock();
        Language::resetMock();
        Carrier::resetMock();
        $this->controller = new TestableGlobalDataController();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makePrestaCustomerGroup(array $overrides = []): array
    {
        return array_merge([
            'id_group'            => 3,
            'reduction'           => '0.000000',
            'price_display_method' => 0,
            'show_prices'         => 1,
            'name'                => 'Wholesale',
        ], $overrides);
    }

    // =========================================================================
    // createJtlCustomerGroupI18n
    // =========================================================================

    public function testCreateJtlCustomerGroupI18nMapsNameCorrectly(): void
    {
        $data   = $this->makePrestaCustomerGroup(['name' => 'Retailer']);
        $result = $this->controller->exposeCreateJtlCustomerGroupI18n($data);

        self::assertInstanceOf(JtlCustomerGroupI18n::class, $result);
        self::assertSame('Retailer', $result->getName());
    }

    public function testCreateJtlCustomerGroupI18nMapsLanguageIsoFromContext(): void
    {
        $data   = $this->makePrestaCustomerGroup();
        $result = $this->controller->exposeCreateJtlCustomerGroupI18n($data);

        // getJtlLanguageIsoFromLanguageId always returns 'eng'
        self::assertSame('eng', $result->getLanguageIso());
    }

    public function testCreateJtlCustomerGroupI18nWithEmptyNameReturnsEmptyString(): void
    {
        $data   = $this->makePrestaCustomerGroup(['name' => '']);
        $result = $this->controller->exposeCreateJtlCustomerGroupI18n($data);

        self::assertSame('', $result->getName());
    }

    public function testCreateJtlCustomerGroupI18nWithSpecialCharactersInName(): void
    {
        $data   = $this->makePrestaCustomerGroup(['name' => 'Händler & Partner']);
        $result = $this->controller->exposeCreateJtlCustomerGroupI18n($data);

        self::assertSame('Händler & Partner', $result->getName());
    }

    public function testCreateJtlCustomerGroupI18nIgnoresOtherGroupFields(): void
    {
        // Only name and languageIso are part of the i18n object;
        // id_group, show_prices etc. are handled at the group level, not i18n level.
        $data   = $this->makePrestaCustomerGroup(['name' => 'VIP', 'show_prices' => 0]);
        $result = $this->controller->exposeCreateJtlCustomerGroupI18n($data);

        self::assertSame('VIP', $result->getName());
        self::assertSame('eng', $result->getLanguageIso());
    }

    public function testCreateJtlCustomerGroupI18nWithLongName(): void
    {
        $longName = str_repeat('A', 200);
        $data     = $this->makePrestaCustomerGroup(['name' => $longName]);
        $result   = $this->controller->exposeCreateJtlCustomerGroupI18n($data);

        self::assertSame($longName, $result->getName());
    }

    // =========================================================================
    // push
    // =========================================================================

    public function testPushReturnsSameModel(): void
    {
        $model  = new JtlGlobalData();
        $result = $this->controller->push($model);
        self::assertSame($model, $result);
    }

    // =========================================================================
    // createJtlCurrency
    // =========================================================================

    public function testCreateJtlCurrencyReturnsCurrencyObject(): void
    {
        Currency::$mockCurrencyData = ['iso_code' => 'EUR', 'conversion_rate' => '1.0'];
        $result = $this->controller->exposeCreateJtlCurrency();
        self::assertInstanceOf(JtlCurrency::class, $result);
        self::assertSame('EUR', $result->getIso());
        self::assertTrue($result->getIsDefault());
    }

    public function testCreateJtlCurrencyThrowsWhenDefaultCurrencyNotArray(): void
    {
        Currency::$mockCurrencyData = false;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Default currency not found');
        $this->controller->exposeCreateJtlCurrency();
    }

    // =========================================================================
    // getCustomerGroups
    // =========================================================================

    public function testGetCustomerGroupsReturnsEmptyArrayWhenNoGroups(): void
    {
        $result = $this->controller->exposeGetCustomerGroups();
        self::assertSame([], $result);
    }

    public function testGetCustomerGroupsReturnsMappedGroupObjects(): void
    {
        Group::$mockGroups = [
            ['id_group' => 3, 'reduction' => '0.000000', 'price_display_method' => 0, 'show_prices' => 1, 'name' => 'Wholesale'],
        ];
        $result = $this->controller->exposeGetCustomerGroups();
        self::assertCount(1, $result);
        self::assertInstanceOf(JtlCustomerGroup::class, $result[0]);
        self::assertSame('3', $result[0]->getId()->getEndpoint());
        self::assertTrue($result[0]->getApplyNetPrice());
    }

    public function testGetCustomerGroupsApplyNetPriceFalseWhenShowPricesIsZero(): void
    {
        Group::$mockGroups = [
            ['id_group' => 5, 'reduction' => '0.0', 'price_display_method' => 0, 'show_prices' => 0, 'name' => 'Guests'],
        ];
        $result = $this->controller->exposeGetCustomerGroups();
        self::assertFalse($result[0]->getApplyNetPrice());
    }

    // =========================================================================
    // getTaxRates
    // =========================================================================

    public function testGetTaxRatesReturnsEmptyArrayWhenNoTaxes(): void
    {
        $result = $this->controller->exposeGetTaxRates();
        self::assertSame([], $result);
    }

    public function testGetTaxRatesReturnsMappedRates(): void
    {
        Tax::$mockTaxes = [['rate' => '19.0']];
        $result          = $this->controller->exposeGetTaxRates();
        self::assertCount(1, $result);
        self::assertInstanceOf(JtlTaxRate::class, $result[0]);
        self::assertSame(19.0, $result[0]->getRate());
    }

    // =========================================================================
    // getLanguages
    // =========================================================================

    public function testGetLanguagesReturnsEmptyArrayWhenNoLanguages(): void
    {
        $result = $this->controller->exposeGetLanguages();
        self::assertSame([], $result);
    }

    public function testGetLanguagesReturnsMappedLanguages(): void
    {
        Language::$mockLanguagesList = [
            ['id_lang' => 1, 'name' => 'English', 'iso_code' => 'en'],
        ];
        $result = $this->controller->exposeGetLanguages();
        self::assertCount(1, $result);
        self::assertInstanceOf(JtlLanguage::class, $result[0]);
        self::assertSame('English', $result[0]->getNameEnglish());
        self::assertSame('en', $result[0]->getLanguageIso());
        self::assertTrue($result[0]->getIsDefault()); // id_lang=1 matches getPrestaContextLanguageId()=1
    }

    // =========================================================================
    // getShippingMethods
    // =========================================================================

    public function testGetShippingMethodsReturnsEmptyArrayWhenNoCarriers(): void
    {
        $result = $this->controller->exposeGetShippingMethods();
        self::assertSame([], $result);
    }

    public function testGetShippingMethodsReturnsMappedMethods(): void
    {
        Carrier::$mockCarriers = [
            ['id_carrier' => '2', 'name' => 'DHL', 'url' => ''],
        ];
        $result = $this->controller->exposeGetShippingMethods();
        self::assertCount(1, $result);
        self::assertInstanceOf(JtlShippingMethod::class, $result[0]);
        self::assertSame('2', $result[0]->getId()->getEndpoint());
        self::assertSame('DHL', $result[0]->getName());
    }

    // =========================================================================
    // pull
    // =========================================================================

    public function testPullReturnsOneGlobalDataObject(): void
    {
        Currency::$mockCurrencyData = ['iso_code' => 'EUR', 'conversion_rate' => '1.0'];

        $filter = new QueryFilter();
        $result = $this->controller->pull($filter);

        self::assertCount(1, $result);
        self::assertInstanceOf(JtlGlobalData::class, $result[0]);
    }
}
