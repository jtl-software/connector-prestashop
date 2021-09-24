<?php

namespace jtl\Connector\Presta\Controller;

class ProductVariationI18n extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $result = $this->db->executeS(
            '
			SELECT agl.*
			FROM '._DB_PREFIX_.'attribute_group_lang agl
			LEFT JOIN '._DB_PREFIX_.'lang AS l ON l.id_lang = agl.id_lang
            WHERE l.id_lang IS NOT NULL AND agl.id_attribute_group = '.$data['id']
        );

        $return = [];

        foreach ($result as $lData) {
            $model = $this->mapper->toHost($lData);

            $return[] = $model;
        }

        return $return;
    }
}
