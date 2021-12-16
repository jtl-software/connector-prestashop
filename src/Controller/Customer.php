<?php

namespace jtl\Connector\Presta\Controller;

class Customer extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $result = $this->db->executeS(
            '
			SELECT c.*, c.id_customer AS cid, a.*, co.iso_code
			FROM ' . _DB_PREFIX_ . 'customer c
			LEFT JOIN ' . _DB_PREFIX_ . 'address a ON a.id_customer=c.id_customer
			LEFT JOIN ' . _DB_PREFIX_ . 'country co ON co.id_country=a.id_country
			LEFT JOIN jtl_connector_link_customer l ON c.id_customer = l.endpoint_id
            WHERE l.host_id IS NULL AND a.id_address IS NOT NULL
            GROUP BY c.id_customer
            LIMIT ' . $limit
        );

        $return = [];

        foreach ($result as $data) {
            $model = $this->mapper->toHost($data);
            $return[] = $model;
        }

        return $return;
    }

    /*
    public function pushData($data)
    {
        $customer = $this->mapper->toEndpoint($data);

        $customer->save();

        $id = $customer->id;

        $data->getId()->setEndpoint($id);

        return $data;
    }
    */

    public function getStats()
    {
        return $this->db->getValue('
			SELECT COUNT(*) 
			FROM ' . _DB_PREFIX_ . 'customer c
			LEFT JOIN jtl_connector_link_customer l ON c.id_customer = l.endpoint_id
			LEFT JOIN ' . _DB_PREFIX_ . 'address a ON c.id_customer = a.id_customer
            WHERE l.host_id IS NULL AND a.id_address IS NOT NULL
            GROUP BY c.id_customer
        ');
    }
}
