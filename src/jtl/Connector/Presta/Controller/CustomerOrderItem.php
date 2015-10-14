<?php
namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Model\CustomerOrderItem as CustomerorderItemModel;
use jtl\Connector\Model\Identity;

class CustomerOrderItem extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $result = $this->db->executeS('
			SELECT i.*
			FROM '._DB_PREFIX_.'order_detail i
			WHERE i.id_order = '.$data['id_order']
        );

        $return = array();

        foreach ($result as $iData) {
            $model = $this->mapper->toHost($iData);

            $return[] = $model;
        }

        $shipping = new CustomerOrderItemModel();
        $shipping->setId(new Identity('shipping_'.$data['id_order']));
        $shipping->setCustomerOrderId(new Identity($data['id_order']));
        $shipping->setType('shipping');
        $shipping->setName($data['shippingMethod']);
        $shipping->setPrice(floatval($data['total_shipping_tax_excl']));
        $shipping->setVat(floatval($data['carrier_tax_rate']));
        $shipping->setQuantity(1);

        $return[] = $shipping;

        return $return;
    }
}
