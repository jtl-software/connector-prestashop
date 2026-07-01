<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Controller;

use Jtl\Connector\Core\Model\AbstractModel;

abstract class AbstractPushController extends AbstractController
{
    /**
     * @param AbstractModel $model
     * @return AbstractModel
     */
    final public function push(AbstractModel $model): AbstractModel
    {
        return $this->doPush($model);
    }

    /**
     * @param AbstractModel $model
     * @return AbstractModel
     */
    abstract protected function doPush(AbstractModel $model): AbstractModel;
}
