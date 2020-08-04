<?php

namespace jtl\Connector\Presta\Controller;

class ProductVariationI18n extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $result = $this->db->executeS(
            '
			SELECT l.*
			FROM '._DB_PREFIX_.'attribute_group_lang l
			WHERE l.id_attribute_group = '.$data['id']
        );

        $return = [];

        foreach ($result as $lData) {
            $model = $this->mapper->toHost($lData);

            $return[] = $model;
        }

        return $return;
    }
}
