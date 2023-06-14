<?php

namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Presta\Utils\Utils;

class Language extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $return = [];

        foreach (Utils::getInstance()->getLanguages() as $data) {
            $model = $this->mapper->toHost((array) $data);

            $return[] = $model;
        }

        return $return;
    }
}
