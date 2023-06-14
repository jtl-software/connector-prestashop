<?php

namespace jtl\Connector\Presta\Utils;

use jtl\Connector\Model\ProductAttr;
use jtl\Connector\Model\ProductAttrI18n;
use jtl\Connector\Payment\PaymentTypes;
use jtl\Connector\Session\SessionHelper;
use jtl\Connector\Core\Utilities\Language;

class Utils
{
    private static $instance;
    private $session = null;

    public function __construct()
    {
        $this->session = new SessionHelper("prestaConnector");
    }

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param $id
     * @param null $padValue
     * @return array
     */
    public static function explodeProductEndpoint($id, $padValue = null)
    {
        return \array_pad(\explode('_', $id, 2), 2, $padValue);
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

    /**
     * @param $html
     * @return string|string[]|null
     */
    public static function cleanHtml($html)
    {
        $events = 'onmousedown|onmousemove|onmmouseup|onmouseover|onmouseout|onload|onunload|onfocus|onblur|onchange';
        $events .= '|onsubmit|ondblclick|onclick|onkeydown|onkeyup|onkeypress|onmouseenter|onmouseleave|onerror|onselect|onreset|onabort|ondragdrop|onresize|onactivate|onafterprint|onmoveend';
        $events .= '|onafterupdate|onbeforeactivate|onbeforecopy|onbeforecut|onbeforedeactivate|onbeforeeditfocus|onbeforepaste|onbeforeprint|onbeforeunload|onbeforeupdate|onmove';
        $events .= '|onbounce|oncellchange|oncontextmenu|oncontrolselect|oncopy|oncut|ondataavailable|ondatasetchanged|ondatasetcomplete|ondeactivate|ondrag|ondragend|ondragenter|onmousewheel';
        $events .= '|ondragleave|ondragover|ondragstart|ondrop|onerrorupdate|onfilterchange|onfinish|onfocusin|onfocusout|onhashchange|onhelp|oninput|onlosecapture|onmessage|onmouseup|onmovestart';
        $events .= '|onoffline|ononline|onpaste|onpropertychange|onreadystatechange|onresizeend|onresizestart|onrowenter|onrowexit|onrowsdelete|onrowsinserted|onscroll|onsearch|onselectionchange';
        $events .= '|onselectstart|onstart|onstop';

        $html = \preg_replace('/<[\s]*script/ims', '', $html);
        $html = \preg_replace('/(' . $events . ')[\s]*=/ims', '', $html);
        $html = \preg_replace('/.*script\:/ims', '', $html);

        if ((bool)\Configuration::get('PS_USE_HTMLPURIFIER') === false) {
            return $html;
        }

        $removeTags = [
            'form',
            'input',
            'embed',
            'object'
        ];

        if ((bool)\Configuration::get('PS_ALLOW_HTML_IFRAME') !== true) {
            $removeTags[] = 'i?frame';
        }

        $html = \preg_replace(\sprintf('/<[\s]*(%s)/ims', \join('|', $removeTags)), '', $html);

        return $html;
    }

    /**
     * @param string $attributeName
     * @param string $languageISO
     * @param ProductAttr ...$productAttrs
     * @return ProductAttrI18n|null
     */
    public static function findAttributeByLanguageISO(
        string $attributeName,
        string $languageISO,
        ProductAttr ...$productAttrs
    ): ?ProductAttrI18n {
        $attribute = null;
        foreach ($productAttrs as $productAttr) {
            foreach ($productAttr->getI18ns() as $productAttrI18n) {
                if (
                    $productAttrI18n->getLanguageISO() === $languageISO && $attributeName === $productAttrI18n->getName(
                    )
                ) {
                    $attribute = $productAttrI18n;
                    break 2;
                }
            }
        }
        return $attribute;
    }

    /**
     * @param $module
     * @return mixed|string
     */
    public static function mapPaymentModuleCode($module)
    {
        $mappedPaymentModuleCode = null;

        switch ($module) {
            case 'ps_wirepayment':
                $mappedPaymentModuleCode = PaymentTypes::TYPE_BANK_TRANSFER;
                break;
            case 'ps_cashondelivery':
                $mappedPaymentModuleCode = PaymentTypes::TYPE_CASH_ON_DELIVERY;
                break;
            case 'paypal':
                $mappedPaymentModuleCode = PaymentTypes::TYPE_PAYPAL;
                break;
            case 'klarnapaymentsofficial':
                $mappedPaymentModuleCode = PaymentTypes::TYPE_KLARNA;
                break;
        }

        return $mappedPaymentModuleCode;
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

    public function getLanguages()
    {
        if (\is_null($this->session->languages)) {
            $languages = \Language::getLanguages(false);

            foreach ($languages as &$lang) {
                $iso3 = Language::convert($lang['language_code']);
                if (empty($iso3)) {
                    $locale = \str_replace('-', '_', $lang['locale']);
                    $iso3 = Language::map($locale);
                }

                $lang['iso3'] = $iso3;
            }

            $this->session->languages = $languages;
        }

        return $this->session->languages;
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
        $address->id_country = (int)$context->country->id;
        $address->id_state = 0;
        $address->postcode = 0;

        $tax_manager = \TaxManagerFactory::getManager(
            $address,
            \Product::getIdTaxRulesGroupByIdProduct((int)$id, $context)
        );

        return $tax_manager->getTaxCalculator()->getTotalRate();
    }
}
