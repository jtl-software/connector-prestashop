<?php
namespace jtl\Connector\Presta\Controller;

class Customer extends BaseController
{	
	public function pullData($data, $model, $limit = null)
	{
        $result = $this->db->executeS('
			SELECT c.*, a.*, co.iso_code
			FROM '._DB_PREFIX_.'customer c
			LEFT JOIN '._DB_PREFIX_.'address a ON a.id_customer=c.id_customer
			LEFT JOIN '._DB_PREFIX_.'country co ON co.id_country=a.id_country
			LEFT JOIN jtl_connector_link l ON c.id_customer = l.endpointId AND l.type = 2
            WHERE l.hostId IS NULL
            GROUP BY c.id_customer
            LIMIT '.$limit
        );

		$return = array();

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
			FROM '._DB_PREFIX_.'customer c
			LEFT JOIN jtl_connector_link l ON c.id_customer = l.endpointId AND l.type = 2
            WHERE l.hostId IS NULL
        ');
	}
}
