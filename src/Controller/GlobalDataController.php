<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Controller;

use Jtl\Connector\Core\Controller\PullInterface;
use Jtl\Connector\Core\Controller\PushInterface;
use Jtl\Connector\Core\Model\AbstractModel;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\QueryFilter;
use Jtl\Connector\Core\Model\GlobalData;
use Jtl\Connector\Core\Model\Currency as JtlCurrency;
use Jtl\Connector\Core\Model\CustomerGroup as JtlCustomerGroup;
use Jtl\Connector\Core\Model\CustomerGroupI18n as JtlCustomerGroupI18n;
use Jtl\Connector\Core\Model\TaxRate as JtlTaxRate;
use Jtl\Connector\Core\Model\Language as JtlLanguage;
use Jtl\Connector\Core\Model\ShippingMethod as JtlShippingMethod;

class GlobalDataController extends AbstractController implements PullInterface, PushInterface
{
    /**
     * @param QueryFilter $queryFilter
     * @return array|AbstractModel[]
     */
    public function pull(QueryFilter $queryFilter): array
    {
        $globalData = (new GlobalData())
            ->setCurrencies($this->createJtlCurrency())
            ->setCustomerGroups(...$this->getCustomerGroups())
            ->setTaxRates(...$this->getTaxRates())
            ->setLanguages(...$this->getLanguages())
            ->setShippingMethods(...$this->getShippingMethods());

        return [$globalData];
    }

    /**
     * @param AbstractModel $model
     * @return AbstractModel
     */
    public function push(AbstractModel $model): AbstractModel
    {
        return $model;
    }

    /**
     * @return JtlCurrency
     */
    protected function createJtlCurrency(): JtlCurrency
    {
        $currency = \Currency::getCurrency(\Currency::getDefaultCurrencyId());

        if (!\is_array($currency)) {
            throw new \RuntimeException('Default currency not found');
        }

        return (new JtlCurrency())
            ->setIsDefault(true)
            ->setIso($currency['iso_code'])
            ->setFactor((float)$currency['conversion_rate']);
    }

    /**
     * @return JtlCustomerGroup[]
     */
    protected function getCustomerGroups(): array
    {
        $prestaCustomerGroups = \Group::getGroups($this->getContextPrestaLanguageId());
        $jtlCustomerGroups    = [];


        //TODO: Customer Group I18n hinzufügen
        foreach ($prestaCustomerGroups as $prestaCustomerGroup) {
            $jtlCustomerGroup = (new JtlCustomerGroup())
                ->setId(new Identity((string)$prestaCustomerGroup['id_group']))
                ->addI18n($this->createJtlCustomerGroupI18n($prestaCustomerGroup))
                ->setApplyNetPrice($prestaCustomerGroup['show_prices'] === 1);

            $jtlCustomerGroups[] = $jtlCustomerGroup;
        }

        return $jtlCustomerGroups;
    }

    /**
     * @param array{
     *     id_group: int,
     *     reduction: string,
     *     price_display_method: int,
     *     show_prices: int,
     *     name: string
     * } $prestaCustomerGroup
     * @return JtlCustomerGroupI18n
     * @throws \PrestaShopDatabaseException
     */
    protected function createJtlCustomerGroupI18n(array $prestaCustomerGroup): JtlCustomerGroupI18n
    {
        return (new JtlCustomerGroupI18n())
            ->setLanguageIso($this->getJtlLanguageIsoFromLanguageId($this->getContextPrestaLanguageId()))
            ->setName($prestaCustomerGroup['name']);
    }

    /**
     * @return JtlTaxRate[]
     */
    protected function getTaxRates(): array
    {
        $prestaTaxes = \Tax::getTaxes($this->getContextPrestaLanguageId());
        $jtlTaxes    = [];

        foreach ($prestaTaxes as $prestaTax) {
            $jtlTaxes[] = (new JtlTaxRate())
                ->setRate((float)$prestaTax['rate']);
        }

        return \array_unique($jtlTaxes, \SORT_REGULAR);
    }

    /**
     * @return JtlLanguage[]
     */
    protected function getLanguages(): array
    {
        $prestaLanguages = \Language::getLanguages();
        $jtlLanguages    = [];

        foreach ($prestaLanguages as $prestaLanguage) {
            if (\is_array($prestaLanguage)) {
                $jtlLanguage = (new JtlLanguage())
                    ->setNameEnglish($prestaLanguage['name'])
                    ->setNameGerman($prestaLanguage['name'])
                    ->setLanguageIso($prestaLanguage['iso_code'])
                    ->setIsDefault($prestaLanguage['id_lang'] === $this->getContextPrestaLanguageId());

                $jtlLanguages[] = $jtlLanguage;
            }
        }

        return $jtlLanguages;
    }

    /**
     * @return JtlShippingMethod[]
     */
    protected function getShippingMethods(): array
    {
        $prestaShippingMethods = \Carrier::getCarriers($this->getContextPrestaLanguageId());
        $jtlShippingMethods    = [];

        foreach ($prestaShippingMethods as $prestaShippingMethod) {
            $jtlShippingMethod = (new JtlShippingMethod())
                ->setId(new Identity((string)$prestaShippingMethod['id_carrier']))
                ->setName($prestaShippingMethod['name']);

            $jtlShippingMethods[] = $jtlShippingMethod;
        }

        return $jtlShippingMethods;
    }
}
