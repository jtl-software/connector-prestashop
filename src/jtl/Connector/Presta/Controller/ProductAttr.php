<?php
namespace jtl\Connector\Presta\Controller;

class ProductAttr extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $productId = $model->getId()->getEndpoint();

        $result = $this->db->executeS('
			SELECT p.*
			FROM '._DB_PREFIX_.'feature_product p
			WHERE p.id_product= '.$data['id_product']
        );

        $return = array();

        foreach ($result as $lData) {
            $lData['id_product'] = $productId;
            $model = $this->mapper->toHost($lData);

            $return[] = $model;
        }

        return $return;
    }
}
