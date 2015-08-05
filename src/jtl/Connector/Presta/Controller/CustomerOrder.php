<?php
namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Model\CustomerOrder as CustomerOrderModel;

class CustomerOrder extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $result = $this->db->executeS('
			SELECT o.*, c.iso_code AS currency, s.name AS shippingMethod
			FROM '._DB_PREFIX_.'orders o
			LEFT JOIN '._DB_PREFIX_.'currency c ON c.id_currency = o.id_currency
			LEFT JOIN '._DB_PREFIX_.'carrier s ON s.id_carrier = o.id_carrier
			LEFT JOIN jtl_connector_link l ON o.id_order = l.endpointId AND l.type = 4
            WHERE l.hostId IS NULL
            LIMIT '.$limit
        );

        $return = array();

        foreach ($result as $data) {
            $model = $this->mapper->toHost($data);

            $this->setStates($data['id_order'], $model);

            $return[] = $model;
        }

        return $return;
    }

    public function getStats()
    {
        return $this->db->getValue('
			SELECT COUNT(*)
			FROM '._DB_PREFIX_.'orders o
			LEFT JOIN jtl_connector_link l ON o.id_order = l.endpointId AND l.type = 4
            WHERE l.hostId IS NULL
        ');
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
