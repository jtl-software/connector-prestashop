<?php
namespace jtl\Connector\Presta\Controller;

class ProductVariationValueI18n extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $result = $this->db->executeS('
			SELECT l.*
			FROM '._DB_PREFIX_.'attribute_lang l
			WHERE l.id_attribute = '.$data['id']
        );

        $return = array();

        foreach ($result as $lData) {
            $model = $this->mapper->toHost($lData);

            $return[] = $model;
        }

        return $return;
    }
}
