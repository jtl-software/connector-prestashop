<?php
namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Presta\Mapper\ProductVarCombi;

class Product extends BaseController
{	
	public function pullData($data, $model, $limit = null)
	{
		$limit = 25;

		$return = array();

        $result = $this->db->executeS('
			SELECT * FROM '._DB_PREFIX_.'product p
			LEFT JOIN jtl_connector_link l ON CAST(p.id_product AS CHAR) = l.endpointId AND l.type = 64
            WHERE l.hostId IS NULL 
            LIMIT '.$limit
        );

        foreach ($result as $data) {
			$model = $this->mapper->toHost($data);

			$return[] = $model;
		}

		$resultVars = $this->db->executeS('
			SELECT p.*, pr.price AS pPrice FROM '._DB_PREFIX_.'product_attribute p
			LEFT JOIN '._DB_PREFIX_.'product pr ON pr.id_product = p.id_product
			LEFT JOIN jtl_connector_link l ON CONCAT(p.id_product, "_", p.id_product_attribute) = l.endpointId AND l.type = 64
            WHERE l.hostId IS NULL
            LIMIT '.$limit
		);

		foreach ($resultVars as $data) {
			$model = $this->mapper->toHost($data);

			$return[] = $model;
		}

		return $return;
	}

	public function getStats()
	{
		$count = $this->db->getValue('
			SELECT COUNT(*) 
			FROM '._DB_PREFIX_.'product p
			LEFT JOIN jtl_connector_link l ON CAST(p.id_product AS CHAR) = l.endpointId AND l.type = 64
            WHERE l.hostId IS NULL
        ');

        $countVars = $this->db->getValue('
            SELECT COUNT(*)
            FROM '._DB_PREFIX_.'product_attribute p
			LEFT JOIN jtl_connector_link l ON CONCAT(p.id_product, "_", p.id_product_attribute) = l.endpointId AND l.type = 64
            WHERE l.hostId IS NULL
        ');

        return ($count + $countVars);
	}
}
