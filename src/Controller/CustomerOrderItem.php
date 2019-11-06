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

        $cartRules = $this->db->executeS('
			SELECT r.*
			FROM '._DB_PREFIX_.'order_cart_rule r
			WHERE r.id_order = '.$data['id_order']
        );

        foreach ($cartRules as $rule) {
            $item = new CustomerorderItemModel();
            $item->setId(new Identity('rule_'.$rule['id_order_cart_rule']));
            $item->setCustomerOrderId(new Identity($data['id_order']));
            $item->setName($rule['name']);
            $item->setPrice(floatval(-$rule['value_tax_excl']));
            $item->setPriceGross(floatval(-$rule['value']));
            $item->setQuantity(1);
            $item->setType(CustomerorderItemModel::TYPE_COUPON);

            $return[] = $item;
        }

        $shipping = new CustomerOrderItemModel();
        $shipping->setId(new Identity('shipping_'.$data['id_order']));
        $shipping->setCustomerOrderId(new Identity($data['id_order']));
        $shipping->setType(CustomerorderItemModel::TYPE_SHIPPING);
        $shipping->setName($data['shippingMethod']);
        $shipping->setPrice(floatval($data['total_shipping_tax_excl']));
        $shipping->setPriceGross(floatval($data['total_shipping_tax_incl']));
        $shipping->setVat(floatval($data['carrier_tax_rate']));
        $shipping->setQuantity(1);

        $return[] = $shipping;

        return $return;
    }
}
