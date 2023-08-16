<?php

namespace jtl\Connector\Presta\Controller;

class ProductVariationI18n extends AbstractController
{
    public function pullData($data, $model, $limit = null)
    {
        $result = $this->getLanguageData('attribute_group_lang', 'id_attribute_group', (int)$data['id']);

        $return = [];

        foreach ($result as $lData) {
            $model = $this->mapper->toHost($lData);

            $return[] = $model;
        }

        return $return;
    }
}
