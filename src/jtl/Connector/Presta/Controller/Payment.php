<?php
namespace jtl\Connector\Presta\Controller;

class Payment extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $result = $this->db->executeS('
			SELECT p.*, o.id_order
			FROM '._DB_PREFIX_.'order_payment p
			LEFT JOIN '._DB_PREFIX_.'orders o ON o.reference = p.order_reference
			LEFT JOIN jtl_connector_link l ON p.id_order_payment = l.endpointId AND l.type = 512
            WHERE l.hostId IS NULL
            LIMIT '.$limit
        );

        $return = array();

        foreach ($result as $data) {
            $model = $this->mapper->toHost($data);

            $return[] = $model;
        }

        return $return;
    }
}
