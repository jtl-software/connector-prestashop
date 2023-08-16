<?php

namespace jtl\Connector\Presta\Controller;

class GlobalData extends AbstractController
{
    public function pullData($data, $model, $limit = null)
    {
        $model = $this->mapper->toHost($data);

        return [$model];
    }
}
