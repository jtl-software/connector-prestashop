<?php
namespace jtl\Connector\Presta\Controller;

class CustomerGroup extends BaseController {
    public function pullData($data, $model, $limit = null)
    {
        $return = array();

        foreach (\Group::getGroups(1) as $gData) {
            $model = $this->mapper->toHost($gData);

            $return[] = $model;
        }

        return $return;
    }
}
