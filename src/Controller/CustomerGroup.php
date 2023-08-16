<?php

namespace jtl\Connector\Presta\Controller;

class CustomerGroup extends AbstractController
{
    public function pullData($data, $model, $limit = null)
    {
        $return = [];

        foreach (\Group::getGroups(1) as $gData) {
            $model = $this->mapper->toHost($gData);

            $return[] = $model;
        }

        return $return;
    }
}
