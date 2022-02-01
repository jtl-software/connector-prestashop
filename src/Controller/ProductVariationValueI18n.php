<?php

namespace jtl\Connector\Presta\Controller;

class ProductVariationValueI18n extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $result = $this->getLanguageData('attribute_lang', 'id_attribute', (int)$data['id']);

        $return = [];

        foreach ($result as $lData) {
            $model = $this->mapper->toHost($lData);

            $return[] = $model;
        }

        return $return;
    }
}
