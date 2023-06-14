<?php

namespace jtl\Connector\Presta\Controller;

class ShippingMethod extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $return = [];

        foreach (\Carrier::getCarriers(null, true) as $data) {
            $model = $this->mapper->toHost((array)$data);

            $return[] = $model;
        }

        return $return;
    }
}
