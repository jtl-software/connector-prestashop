<?php

namespace jtl\Connector\Presta\Controller;

class CustomerOrderBillingAddress extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $result = $this->db->executeS(
            '
			SELECT a.*, c.iso_code AS countryIso, s.name AS state, cu.id_gender, cu.email
			FROM ' . \_DB_PREFIX_ . 'address a
			LEFT JOIN ' . \_DB_PREFIX_ . 'country c ON c.id_country = a.id_country
			LEFT JOIN ' . \_DB_PREFIX_ . 'state s ON s.id_state = a.id_state
			LEFT JOIN ' . \_DB_PREFIX_ . 'customer cu ON cu.id_customer = a.id_customer
            WHERE a.id_address = ' . $data['id_address_invoice']
        );

        return $this->mapper->toHost($result[0]);
    }
}
