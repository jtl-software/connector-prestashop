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
        'priceGross' => 'unit_price_tax_incl',
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
        if ($data['total_price_tax_excl'] <= 0) {
            return 0;
        }
        
        return round(floatval($data['tax_rate'] == 0 ? (100 / $data['total_price_tax_excl'] * $data['total_price_tax_incl']) - 100 : $data['tax_rate']), 4);
    }
}
