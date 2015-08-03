<?php
namespace jtl\Connector\Presta\Controller;

class ProductI18n extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $result = $this->db->executeS('
			SELECT p.*
			FROM '._DB_PREFIX_.'product_lang p
			WHERE p.id_product = '.$data['id_product']
        );

        $return = array();

        foreach ($result as $data) {
            $model = $this->mapper->toHost($data);

            $return[] = $model;
        }

        return $return;
    }
}
