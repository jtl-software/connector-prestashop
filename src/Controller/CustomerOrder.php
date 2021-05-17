<?php

namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Model\CustomerOrder as CustomerOrderModel;

class CustomerOrder extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $query = '
            SELECT IF((SELECT COUNT(`reference`)FROM `ps_orders` WHERE `reference` = `o`.`reference`) > 1, CONCAT(`o`.`reference`, "-", `o`.`id_order`), `o`.`reference`) `order_number`, 
                o.*, c.iso_code AS currency, s.name AS shippingMethod
			FROM '._DB_PREFIX_.'orders o
			LEFT JOIN '._DB_PREFIX_.'currency c ON c.id_currency = o.id_currency
			LEFT JOIN '._DB_PREFIX_.'carrier s ON s.id_carrier = o.id_carrier
			LEFT JOIN jtl_connector_link_customer_order l ON o.id_order = l.endpoint_id
            WHERE l.host_id IS NULL';

        if (!empty(\Configuration::get('jtlconnector_from_date'))) {
            $query .= ' && o.date_add >= "'.\Configuration::get('jtlconnector_from_date').'"';
        }

        $result = $this->db->executeS(
            $query . ' LIMIT '.$limit
        );

        $return = [];

        foreach ($result as $data) {
            $model = $this->mapper->toHost($data);

            $this->setStates($data['id_order'], $model);

            $return[] = $model;
        }

        return $return;
    }

    public function getStats()
    {
        $query = 'SELECT COUNT(*)
			FROM '._DB_PREFIX_.'orders o
			LEFT JOIN jtl_connector_link_customer_order l ON o.id_order = l.endpoint_id
            WHERE l.host_id IS NULL';

        if (!empty(\Configuration::get('jtlconnector_from_date'))) {
            $query .= ' && o.date_add >= "'.\Configuration::get('jtlconnector_from_date').'"';
        }

        return $this->db->getValue($query);
    }

    private function setStates($id, &$model)
    {
        $order = new \Order($id);
        $model->setPaymentStatus($order->hasBeenPaid() == 1 ? CustomerOrderModel::PAYMENT_STATUS_COMPLETED : CustomerOrderModel::PAYMENT_STATUS_UNPAID);
        if ($order->hasBeenDelivered() == 1 || $order->hasBeenShipped() == 1) {
            $model->setStatus(CustomerOrderModel::STATUS_SHIPPED);
        }
    }
}
