<?php

namespace jtl\Connector\Presta\Controller;

class GlobalData extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        return [$this->mapper->toHost($data)];
    }
}
