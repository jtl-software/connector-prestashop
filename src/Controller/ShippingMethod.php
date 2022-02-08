<?php

namespace jtl\Connector\Presta\Controller;

/**
 *
 */
class ShippingMethod extends BaseController
{
    /**
     * @param $data
     * @param $model
     * @param $limit
     * @return array
     */
    public function pullData($data, $model, $limit = null)
    {
        $return = [];

        foreach (\Carrier::getCarriers(null, true) as $carrier) {
            $model = $this->mapper->toHost((array) $carrier);

            $return[] = $model;
        }

        return $return;
    }
}
