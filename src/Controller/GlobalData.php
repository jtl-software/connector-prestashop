<?php
namespace jtl\Connector\Presta\Controller;

class GlobalData extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $model = $this->mapper->toHost($data);

        return array($model);
    }
}
