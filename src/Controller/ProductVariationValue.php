<?php
namespace jtl\Connector\Presta\Controller;

class ProductVariationValue extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        foreach ($data['values'] as $valueData) {
            $model = $this->mapper->toHost($valueData);

            $return[] = $model;
        }

        return $return;
    }
}
