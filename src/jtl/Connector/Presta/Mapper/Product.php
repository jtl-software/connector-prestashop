<?php
namespace jtl\Connector\Presta\Mapper;

use \jtl\Connector\Model\Identity;
use jtl\Connector\Presta\Utils\Utils;

class Product extends BaseMapper
{
	protected $endpointModel = '\Product';
    protected $identity = 'id|id_product';

	protected $pull = array(
		'id' => null,
		'manufacturerId' => 'id_manufacturer',
		'masterProductId' => null,
		'creationDate' => 'date_add',
		'ean' => 'ean13',
		'height' => 'height',
		'isMasterProduct' => null,
		'length' => 'depth',
		'modified' => 'date_upd',
		'shippingWeight' => 'weight',
		'sku' => 'reference',
		'upc' => 'upc',
		'stockLevel' => 'ProductStockLevel',
		'vat' => null,
		'width' => 'width',
		'attributes' => 'ProductAttr',
		'categories' => 'Product2Category',
		'i18ns' => 'ProductI18n',
		'prices' => 'ProductPrice',
		'variations' => 'ProductVariation',
        'availableFrom' => 'available_date',
        'basePriceUnitName' => 'unity',
        'considerStock' => null,
        'isActive' => null,
        'minimumOrderQuantity' => 'minimal_quantity'
	);

	protected $push = array(
        'id_product' => 'id',
        'id_manufacturer' => 'manufacturerId',
        'date_add' => 'creationDate',
        'ean13' => 'ean',
        'height' => 'height',
        'depth' => 'length',
        'date_upd' => 'modified',
        'weight' => 'shippingWeight',
        'reference' => 'sku',
        'upc' => 'upc',
        'id_tax_rules_group' => null,
        'width' => 'width',
        'unity' => 'basePriceUnitName',
        'available_date' => 'availableFrom',
        'active' => 'isActive',
        'minimal_quantity' => 'minimumOrderQuantity',
        'ProductAttr' => 'attributes',
        'ProductI18n' => 'i18ns'
    );

    protected function isActive($data)
    {
        return true;
    }

    protected function id($data)
    {
        if (isset($data['id_product_attribute'])) {
            return new Identity($data['id_product'].'_'.$data['id_product_attribute']);
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

    protected function isMasterProduct($data)
    {
        if (!isset($data['id_product_attribute'])) {
            $count = $this->db->getValue('SELECT COUNT(id_product) FROM ' . _DB_PREFIX_ . 'product_attribute WHERE id_product=' . $data['id_product']);

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

    protected function considerStock($data)
    {
        return true;
    }

    protected function id_tax_rules_group($data)
    {
        $group = $this->db->getValue('
            SELECT r.id_tax_rules_group
            FROM ps_tax t
            LEFT JOIN ps_tax_rule r ON r.id_tax = t.id_tax
            WHERE t.rate = '.$data->getVat().' && id_country = '.\Context::getContext()->country->id.'
            GROUP BY r.id_tax
        ');

        return $group;
    }
}
