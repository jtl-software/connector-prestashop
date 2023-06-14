<?php

namespace jtl\Connector\Presta\Mapper;

use jtl\Connector\Model\Identity;
use jtl\Connector\Presta\Utils\Utils;

class Product extends BaseMapper
{
    protected $endpointModel = '\Product';
    protected $identity      = 'id|id_product';

    protected $pull = [
        'id' => null,
        'manufacturerId' => 'id_manufacturer',
        'masterProductId' => null,
        'creationDate' => 'date_add',
        'ean' => 'ean13',
        'isbn' => 'isbn',
        'height' => 'height',
        'isMasterProduct' => null,
        'length' => 'depth',
        'modified' => 'date_upd',
        'shippingWeight' => 'weight',
        'sku' => 'reference',
        'upc' => 'upc',
        'stockLevel' => 'ProductStockLevel',
        'specialPrices' => 'ProductSpecialPrice',
        'vat' => null,
        'width' => 'width',
        'attributes' => 'ProductAttr',
        'categories' => 'Product2Category',
        'prices' => 'ProductPrice',
        'variations' => 'ProductVariation',
        'i18ns' => 'ProductI18n',
        'availableFrom' => 'available_date',
        'basePriceUnitName' => 'unity',
        'considerStock' => null,
        'permitNegativeStock' => null,
        'isActive' => null,
        'isTopProduct' => 'on_sale',
        'purchasePrice' => 'wholesale_price',
        'minimumOrderQuantity' => 'minimal_quantity',
        'manufacturerNumber' => 'mpn'
    ];

    protected $push = [
        'id_product' => 'id',
        'id_manufacturer' => 'manufacturerId',
        'id_category_default' => null,
        'date_add' => null,
        'ean13' => 'ean',
        'height' => 'height',
        'depth' => 'length',
        'date_upd' => 'modified',
        'weight' => 'shippingWeight',
        'reference' => 'sku',
        'upc' => 'upc',
        'isbn' => 'isbn',
        'out_of_stock' => null,
        'id_tax_rules_group' => null,
        'width' => 'width',
        'unity' => null,
        'available_date' => 'availableFrom',
        'active' => 'isActive',
        'on_sale' => 'isTopProduct',
        'minimal_quantity' => null,
        'ProductI18n' => 'i18ns',
        'wholesale_price' => null,
        'mpn' => 'manufacturerNumber'
    ];

    protected function wholesale_price($data)
    {
        return \round($data->getPurchasePrice(), 4);
    }

    protected function out_of_stock($data)
    {
        if ($data->getConsiderStock() === false || $data->getPermitNegativeStock() === true) {
            return 1;
        }

        return 0;
    }

    protected function date_add($data)
    {
        if (\is_null($data->getCreationDate())) {
            $current = new \DateTime();
            return $current->format('Y-m-d H:i:s');
        }

        return $data->getCreationDate()->format('Y-m-d H:i:s');
    }

    protected function minimal_quantity($data)
    {
        $value = \ceil($data->getMinimumOrderQuantity());
        return $value < 1 ? 1 : $value;
    }

    protected function unity($data)
    {
        $unit = '';
        if ($data->getConsiderBasePrice()) {
            $basePriceQuantity = $data->getBasePriceQuantity() !== 1. ? (string)$data->getBasePriceQuantity() : '';
            $unit              = \sprintf('%s%s', $basePriceQuantity, $data->getBasePriceUnitCode());
        }
        return $unit;
    }

    protected function isActive($data)
    {
        return true;
    }

    protected function id($data)
    {
        if (isset($data['id_product_attribute'])) {
            return new Identity($data['id_product'] . '_' . $data['id_product_attribute']);
        } else {
            return new Identity($data['id_product']);
        }
    }

    protected function masterProductId($data)
    {
        if (isset($data['id_product_attribute'])) {
            return new Identity($data['id_product']);
        }
    }

    protected function id_category_default($data)
    {
        $categories = $data->getCategories();
        if (\count($categories) > 0) {
            $firstCategory = \reset($categories);
            return $firstCategory->getCategoryId()->getEndpoint();
        }

        return null;
    }

    protected function isMasterProduct($data)
    {
        if (!isset($data['id_product_attribute'])) {
            $count = $this->db->getValue('SELECT COUNT(id_product) FROM ' . \_DB_PREFIX_ . 'product_attribute WHERE id_product=' . $data['id_product']);

            if ($count > 0) {
                return true;
            }
        }

        return false;
    }

    protected function vat($data)
    {
        return Utils::getInstance()->getProductTaxRate($data['id_product']);
    }

    protected function permitNegativeStock($data)
    {
        $query = 'SELECT out_of_stock FROM ' . \_DB_PREFIX_ . 'stock_available WHERE id_product=' . $data['id_product'];

        if (!empty($data['id_product_attribute'])) {
            $query .= ' AND id_product_attribute = ' . $data['id_product_attribute'];
        } else {
            $query .= ' AND id_product_attribute = 0';
        }

        $option = $this->db->getValue($query);

        return ($option === false || $option == '0' || $option == '2') ? false : true;
    }

    protected function considerStock($data)
    {
        return true;
    }

    /**
     * @param \jtl\Connector\Model\Product $product
     * @return false|mixed|string|null
     * @throws \PrestaShopDatabaseException
     */
    protected function id_tax_rules_group(\jtl\Connector\Model\Product $product)
    {
        if (!\is_null($product->getTaxClassId()) && !empty($product->getTaxClassId()->getEndpoint())) {
            $taxRulesGroupId = $product->getTaxClassId()->getEndpoint();
        } else {
            $sql =
                'SELECT rg.id_tax_rules_group' . "\n" .
                'FROM %stax_rule r' . "\n" .
                'LEFT JOIN %stax_rules_group rg ON rg.id_tax_rules_group = r.id_tax_rules_group' . "\n" .
                'LEFT JOIN %stax t ON t.id_tax = r.id_tax' . "\n" .
                'WHERE t.rate = %s && r.id_country = %s && rg.deleted = 0 && t.active = 1 && rg.active = 1';

            $taxRulesGroupId = $this->db->getValue(\sprintf($sql, \_DB_PREFIX_, \_DB_PREFIX_, \_DB_PREFIX_, $product->getVat(), \Context::getContext()->country->id));

            if (\count($product->getTaxRates()) > 0 && !\is_null($product->getTaxClassId())) {
                $taxRulesGroupId = $this->findTaxClassId(...$product->getTaxRates()) ?? $taxRulesGroupId;
                //$product->getTaxClassId()->setEndpoint($taxRulesGroupId);
            }
        }

        return $taxRulesGroupId;
    }

    /**
     * @param \jtl\Connector\Model\TaxRate ...$jtlTaxRates
     * @return mixed|null
     * @throws \PrestaShopDatabaseException
     */
    protected function findTaxClassId(\jtl\Connector\Model\TaxRate ...$jtlTaxRates)
    {
        $conditions = [];
        foreach ($jtlTaxRates as $taxRate) {
            $conditions[] = \sprintf("(iso_code = '%s' AND rate='%s')", $taxRate->getCountryIso(), \number_format($taxRate->getRate(), 3));
        }

        $foundTaxClasses = $this->db->query(\sprintf(
            'SELECT id_tax_rules_group, COUNT(id_tax_rules_group) AS hits
                    FROM %stax_rule
                    LEFT JOIN %stax ON %stax.id_tax = %stax_rule.id_tax
                    LEFT JOIN %scountry ON %scountry.id_country = %stax_rule.id_country
                    WHERE %s 
                    GROUP BY id_tax_rules_group
                    ORDER BY hits DESC',
            ...\array_merge(\array_fill(0, 7, \_DB_PREFIX_), [\join(' OR ', $conditions)])
        ))->fetchAll(\PDO::FETCH_ASSOC);

        return $foundTaxClasses[0]['id_tax_rules_group'] ?? null;
    }
}
