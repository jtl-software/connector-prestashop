<?php
namespace jtl\Connector\Presta\Mapper;

use jtl\Connector\Model\Identity;
use jtl\Connector\Presta\Utils\Utils;

class CustomerOrderItem extends BaseMapper
{
    protected $pull = array(
        'id' => 'id_order_detail',
        'productId' => null,
        'customerOrderId' => 'id_order',
        'name' => 'product_name',
        'price' => 'product_price',
        'quantity' => 'product_quantity',
        'sku' => 'product_reference',
        'vat' => null
    );

    protected function productId($data)
    {
        return new Identity($data['product_attribute_id'] == 0 ? $data['product_id'] :  $data['product_id'].'_'.$data['product_attribute_id']);
    }

    protected function vat($data)
    {
        return floatval($data['tax_rate'] == 0 ? Utils::getInstance()->getProductTaxRate($data['product_id']) : $data['tax_rate']);
    }
}
