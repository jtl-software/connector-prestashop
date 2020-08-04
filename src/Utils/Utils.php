<?php

namespace jtl\Connector\Presta\Utils;

use \jtl\Connector\Session\SessionHelper;
use \jtl\Connector\Core\Utilities\Language;

class Utils
{
    private static $instance;
    private $session = null;
    
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    public function __construct()
    {
        $this->session = new SessionHelper("prestaConnector");
    }

    public function getLanguages()
    {
        if (is_null($this->session->languages)) {
            $languages = \Language::getLanguages();

            foreach ($languages as &$lang) {
                $lang['iso3'] = Language::convert($lang['iso_code']);
            }

            $this->session->languages = $languages;
        }

        return $this->session->languages;
    }

    public function getLanguageIdByIso($iso)
    {
        foreach ($this->getLanguages() as $lang) {
            if ($lang['iso3'] === $iso) {
                return $lang['id_lang'];
            }
        }

        return false;
    }

    public function getLanguageIsoById($id)
    {
        foreach ($this->getLanguages() as $lang) {
            if ($lang['id_lang'] === $id) {
                return $lang['iso3'];
            }
        }

        return false;
    }

    public function getProductTaxRate($id)
    {
        $context = \Context::getContext();

        $address = new \Address();
        $address->id_country = (int) $context->country->id;
        $address->id_state = 0;
        $address->postcode = 0;

        $tax_manager = \TaxManagerFactory::getManager($address, \Product::getIdTaxRulesGroupByIdProduct((int) $id, $context));

        return $tax_manager->getTaxCalculator()->getTotalRate();
    }

    /**
     * @param $id
     * @param null $padValue
     * @return array
     */
    public static function explodeProductEndpoint($id, $padValue = null)
    {
        return array_pad(explode('_', $id, 2), 2, $padValue);
    }

    /**
     * @param $result
     * @return array
     */
    public static function groupProductPrices($result)
    {
        $groupPrices = [];
        foreach ($result as $pData) {
            if ($pData['id_customer'] !== '0') {
                //$customerPrices[$pData['id_customer']][] = $pData;
            } elseif ($pData['id_group'] !== '0') {
                $groupPrices[$pData['id_group']][] = $pData;
            } else {
                foreach (\Group::getGroups(1) as $gData) {
                    $groupPrices[$gData['id_group']][] = $pData;
                }
            }
        }
        return $groupPrices;
    }
}
