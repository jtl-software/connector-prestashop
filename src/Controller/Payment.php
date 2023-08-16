<?php

namespace jtl\Connector\Presta\Controller;

class Payment extends AbstractController
{
    public function pullData($data, $model, $limit = null)
    {
        $sql =
            'SELECT p.*, o.id_order, o.module, o.payment' . "\n" .
            'FROM ' . \_DB_PREFIX_ . 'order_payment p' . "\n" .
            'LEFT JOIN ' . \_DB_PREFIX_ . 'orders o ON o.reference = p.order_reference' . "\n" .
            'LEFT JOIN jtl_connector_link_payment l ON p.id_order_payment = l.endpoint_id' . "\n" .
            'LEFT JOIN jtl_connector_link_customer_order co ON co.endpoint_id = o.id_order' . "\n" .
            'WHERE l.host_id IS NULL AND co.endpoint_id IS NOT NULL' . "\n" .
            'LIMIT ' . $limit;

        $result = $this->db->executeS($sql);

        $return = [];

        foreach ($result as $data) {
            $model = $this->mapper->toHost($data);

            $return[] = $model;
        }

        return $return;
    }

    public function getStats()
    {
        return $this->db->getValue(
            '
			SELECT COUNT(*)
			FROM ' . \_DB_PREFIX_ . 'order_payment p
			LEFT JOIN jtl_connector_link_payment l ON p.id_order_payment = l.endpoint_id
            WHERE l.host_id IS NULL AND p.transaction_id != ""
        '
        );
    }
}
