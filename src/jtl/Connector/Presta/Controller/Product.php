<?php
namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Presta\Mapper\ProductVarCombi;

class Product extends BaseController
{
    private static $idCache = array();

    public function pullData($data, $model, $limit = null)
	{
		$limit = $limit < 25 ? $limit : 25;

		$return = array();

        $result = $this->db->executeS('
			SELECT * FROM '._DB_PREFIX_.'product p
			LEFT JOIN jtl_connector_link l ON CAST(p.id_product AS CHAR) = l.endpointId AND l.type = 64
            WHERE l.hostId IS NULL 
            LIMIT '.$limit
        );

		$count = 0;

        foreach ($result as $data) {
			$model = $this->mapper->toHost($data);

			$return[] = $model;

			$count++;
		}

		if ($count < $limit) {
			$resultVars = $this->db->executeS('
                SELECT p.*, pr.price AS pPrice FROM ' . _DB_PREFIX_ . 'product_attribute p
                LEFT JOIN ' . _DB_PREFIX_ . 'product pr ON pr.id_product = p.id_product
                LEFT JOIN jtl_connector_link l ON CONCAT(p.id_product, "_", p.id_product_attribute) = l.endpointId AND l.type = 64
                WHERE l.hostId IS NULL
                LIMIT ' . ($limit - $count)
			);

			foreach ($resultVars as $data) {
				$model = $this->mapper->toHost($data);

				$return[] = $model;
			}
		}

		return $return;
	}

	public function pushData($data)
	{
        if (isset(static::$idCache[$data->getMasterProductId()->getHost()])) {
            $data->getMasterProductId()->setEndpoint(static::$idCache[$data->getMasterProductId()->getHost()]);
        }

        $masterId = $data->getMasterProductId()->getEndpoint();

        if (empty($masterId)) {
            $product = $this->mapper->toEndpoint($data);
            $product->save();

            $id = $product->id_product;
        } else {
            die('varcombi');
        }

		$data->getId()->setEndpoint($id);

        if($id) {
            $data->getStockLevel()->getProductId()->setEndpoint($id);
            $stock = new ProductStockLevel();
            $stock->pushData($data->getStockLevel());
        }

        static::$idCache[$data->getId()->getHost()] = $id;

		return $data;
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
