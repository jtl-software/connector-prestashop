<?php
namespace jtl\Connector\Presta\Mapper;

use \jtl\Connector\Model\Identity;

class Product extends BaseMapper
{
	protected $endpointModel = '\Article';

	protected $pull = array(
		'id' => null,
		'manufacturerId' => 'id_manufacturer',
		'masterProductId' => null,
		'creationDate' => 'dat_add',
		'ean' => 'ean13',
		'height' => 'height',
		'isMasterProduct' => null,
		'length' => 'length',
		'modified' => 'date_upd',
		'productWeight' => 'weight',
		'sku' => 'reference',
		'upc' => 'upc',
		'stockLevel' => 'ProductStockLevel',
		'vat' => null,
		'width' => 'width',
		//'attributes' => 'ProductAttr',
		'categories' => 'Product2Category',
		'i18ns' => 'ProductI18n',
		'prices' => 'ProductPrice',
		//'specialPrices' => 'ProductSpecialPrice',
		//'variations' => 'ProductVariation'
	);

	protected $push = array(
	);

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
        $context = \Context::getContext();

        $address = new \Address();
        $address->id_country = (int) $context->country->id;
        $address->id_state = 0;
        $address->postcode = 0;

        $tax_manager = \TaxManagerFactory::getManager($address, \Product::getIdTaxRulesGroupByIdProduct((int) $data['id_product'], $context));

        return $tax_manager->getTaxCalculator()->getTotalRate();
    }
}
