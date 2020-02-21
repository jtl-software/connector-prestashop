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
        'price' => 'unit_price_tax_excl',
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
        if ((float)$data['total_price_tax_excl'] === (float)0) {
            return 0;
        }

        $rate = $data['tax_rate'];

        if ($rate == 0) {
            $query = sprintf('SELECT t.rate FROM %stax t 
                         LEFT JOIN %sorder_detail_tax dt ON dt.id_tax = t.id_tax
                         WHERE dt.id_order_detail = %s', _DB_PREFIX_, _DB_PREFIX_, $data['id_order_detail']);

            $rate = $this->db->getValue($query);

            if ($rate === false || $rate === null) {
                $rate = (100 / $data['total_price_tax_excl'] * $data['total_price_tax_incl']) - 100;
            }
        }

        return round((float)$rate, 4);
    }
}
