<?php

//phpcs:ignoreFile PSR1.Methods.CamelCapsMethodName.NotCamelCaps

namespace jtl\Connector\Presta\Mapper;

use Faker\Core\DateTime;
use jtl\Connector\Core\Model\Identity;
use jtl\Connector\Presta\Utils\Utils;
use jtl\Connector\Core\Model\Product as CoreProduct;
use Jtl\Connector\Core\Model\TaxRate;

class Product extends BaseMapper
{
    /**
     * @var string|null
     */
    protected ?string $endpointModel = '\Product';
    /**
     * @var string
     */
    protected string $identity      = 'id|id_product';

    /**
     * @var array
     */
    protected array $pull = [
        'id'                   => null,
        'manufacturerId'       => 'id_manufacturer',
        'masterProductId'      => null,
        'creationDate'         => 'date_add',
        'ean'                  => 'ean13',
        'isbn'                 => 'isbn',
        'height'               => 'height',
        'isMasterProduct'      => null,
        'length'               => 'depth',
        'modified'             => 'date_upd',
        'shippingWeight'       => 'weight',
        'sku'                  => 'reference',
        'upc'                  => 'upc',
        'stockLevel'           => 'ProductStockLevel',
        'specialPrices'        => 'ProductSpecialPrice',
        'vat'                  => null,
        'width'                => 'width',
        'attributes'           => 'ProductAttr',
        'categories'           => 'Product2Category',
        'prices'               => 'ProductPrice',
        'variations'           => 'ProductVariation',
        'i18ns'                => 'ProductI18n',
        'availableFrom'        => 'available_date',
        'basePriceUnitName'    => 'unity',
        'considerStock'        => null,
        'permitNegativeStock'  => null,
        'isActive'             => null,
        'isTopProduct'         => 'on_sale',
        'purchasePrice'        => 'wholesale_price',
        'minimumOrderQuantity' => 'minimal_quantity',
        'manufacturerNumber'   => 'mpn'
    ];

    /**
     * @var array
     */
    protected array $push = [
        'id_product'          => 'id',
        'id_manufacturer'     => 'manufacturerId',
        'id_category_default' => null,
        'date_add'            => null,
        'ean13'               => 'ean',
        'height'              => 'height',
        'depth'               => 'length',
        'date_upd'            => 'modified',
        'weight'              => 'shippingWeight',
        'reference'           => 'sku',
        'upc'                 => 'upc',
        'isbn'                => 'isbn',
        'out_of_stock'        => null,
        'id_tax_rules_group'  => null,
        'width'               => 'width',
        'unity'               => null,
        'available_date'      => 'availableFrom',
        'active'              => 'isActive',
        'on_sale'             => 'isTopProduct',
        'minimal_quantity'    => null,
        'ProductI18n'         => 'i18ns',
        'wholesale_price'     => null,
        'mpn'                 => 'manufacturerNumber'
    ];

    /**
     * @param $data
     * @return float
     */
    protected function wholesale_price($data): float
    {
        return \round($data->getPurchasePrice(), 4);
    }

    /**
     * @param $data
     * @return int
     */
    protected function out_of_stock($data): int
    {
        if ($data->getConsiderStock() === false || $data->getPermitNegativeStock() === true) {
            return 1;
        }

        return 0;
    }

    /**
     * @param $data
     * @return string
     */
    protected function date_add($data): string
    {
        if (\is_null($data->getCreationDate())) {
            $current = new \DateTime();
            return $current->format('Y-m-d H:i:s');
        }

        return $data->getCreationDate()->format('Y-m-d H:i:s');
    }

    /**
     * @param $data
     * @return float|int
     */
    protected function minimal_quantity($data): float|int
    {
        $value = \ceil($data->getMinimumOrderQuantity());
        return max($value, 1);
    }

    /**
     * @param $data
     * @return string
     */
    protected function unity($data): string
    {
        $unit = '';
        if ($data->getConsiderBasePrice()) {
            $basePriceQuantity = $data->getBasePriceQuantity() !== 1. ? (string)$data->getBasePriceQuantity() : '';
            $unit              = \sprintf('%s%s', $basePriceQuantity, $data->getBasePriceUnitCode());
        }
        return $unit;
    }

    /**
     * @param $data
     * @return bool
     */
    protected function isActive($data): bool
    {
        return true;
    }

    /**
     * @param $data
     * @return Identity
     */
    protected function id($data): Identity
    {
        if (isset($data['id_product_attribute'])) {
            return new Identity($data['id_product'] . '_' . $data['id_product_attribute']);
        } else {
            return new Identity($data['id_product']);
        }
    }

    /**
     * @param $data
     * @return Identity|null
     */
    protected function masterProductId($data): ?Identity
    {
        if (isset($data['id_product_attribute'])) {
            return new Identity($data['id_product']);
        }

        return null;
    }

    /**
     * @param $data
     * @return int|null
     */
    protected function id_category_default($data): ?int
    {
        $categories = $data->getCategories();
        if (\count($categories) > 0) {
            $firstCategory = \reset($categories);
            return $firstCategory->getCategoryId()->getEndpoint();
        }

        return null;
    }

    /**
     * @param $data
     * @return bool
     */
    protected function isMasterProduct($data): bool
    {
        if (!isset($data['id_product_attribute'])) {
            $count = $this->db->getValue(
                'SELECT COUNT(id_product) 
                 FROM ' . \_DB_PREFIX_ . 'product_attribute 
                 WHERE id_product=' . $data['id_product']
            );

            if ($count > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $data
     * @return float
     */
    protected function vat($data): float
    {
        return Utils::getInstance()->getProductTaxRate($data['id_product']);
    }

    /**
     * @param $data
     * @return bool
     */
    protected function permitNegativeStock($data): bool
    {
        $query = 'SELECT out_of_stock FROM ' . \_DB_PREFIX_ . 'stock_available WHERE id_product=' . $data['id_product'];

        if (!empty($data['id_product_attribute'])) {
            $query .= ' AND id_product_attribute = ' . $data['id_product_attribute'];
        } else {
            $query .= ' AND id_product_attribute = 0';
        }

        $option = $this->db->getValue($query);

        return !(($option === false || $option == '0' || $option == '2'));
    }

    /**
     * @param $data
     * @return bool
     */
    protected function considerStock($data): bool
    {
        return true;
    }

    /**
     * @param CoreProduct $product
     * @return false|mixed|string|null
     * @throws \PrestaShopDatabaseException
     */
    protected function id_tax_rules_group(CoreProduct $product): mixed
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

            $taxRulesGroupId = $this->db->getValue(
                \sprintf(
                    $sql,
                    \_DB_PREFIX_,
                    \_DB_PREFIX_,
                    \_DB_PREFIX_,
                    $product->getVat(),
                    \Context::getContext()->country->id
                )
            );

            if (\count($product->getTaxRates()) > 0 && !\is_null($product->getTaxClassId())) {
                $taxRulesGroupId = $this->findTaxClassId(...$product->getTaxRates()) ?? $taxRulesGroupId;
                //$product->getTaxClassId()->setEndpoint($taxRulesGroupId);
            }
        }

        return $taxRulesGroupId;
    }

    /**
     * @param TaxRate ...$jtlTaxRates
     * @return mixed|null
     * @throws \PrestaShopDatabaseException
     */
    protected function findTaxClassId(TaxRate ...$jtlTaxRates): mixed
    {
        $conditions = [];
        foreach ($jtlTaxRates as $taxRate) {
            $conditions[] = \sprintf(
                "(iso_code = '%s' AND rate='%s')",
                $taxRate->getCountryIso(),
                \number_format($taxRate->getRate(), 3)
            );
        }

        $foundTaxClasses = $this->db->query(
            \sprintf(
                'SELECT id_tax_rules_group, COUNT(id_tax_rules_group) AS hits
                    FROM %stax_rule
                    LEFT JOIN %stax ON %stax.id_tax = %stax_rule.id_tax
                    LEFT JOIN %scountry ON %scountry.id_country = %stax_rule.id_country
                    WHERE %s 
                    GROUP BY id_tax_rules_group
                    ORDER BY hits DESC',
                ...\array_merge(\array_fill(0, 7, \_DB_PREFIX_), [\join(' OR ', $conditions)])
            )
        )->fetchAll(\PDO::FETCH_ASSOC);

        return $foundTaxClasses[0]['id_tax_rules_group'] ?? null;
    }
}
