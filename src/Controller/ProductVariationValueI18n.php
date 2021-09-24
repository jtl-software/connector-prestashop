<?php

namespace jtl\Connector\Presta\Controller;

class ProductVariationValueI18n extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $result = $this->db->executeS(
            '
			SELECT al.*
			FROM '._DB_PREFIX_.'attribute_lang al
			LEFT JOIN '._DB_PREFIX_.'lang AS l ON l.id_lang = al.id_lang
            WHERE l.id_lang IS NOT NULL AND al.id_attribute = '.$data['id']
        );

        $return = [];

        foreach ($result as $lData) {
            $model = $this->mapper->toHost($lData);

            $return[] = $model;
        }

        return $return;
    }
}
