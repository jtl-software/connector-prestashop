<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Controller;

use Jtl\Connector\Core\Controller\PushInterface;
use Jtl\Connector\Core\Model\AbstractModel;
use Jtl\Connector\Core\Model\Product as JtlProduct;
use jtl\Connector\Presta\Utils\Utils;

class ProductStockLevelController extends AbstractController implements PushInterface
{
    /**
     * @param AbstractModel $model
     * @return AbstractModel
     */
    public function push(AbstractModel $model): AbstractModel
    {
        /** @var JtlProduct $model */
        $endpoint = $model->getId()->getEndpoint();

        if (!empty($endpoint)) {
            list($productId, $combiId) = Utils::explodeProductEndpoint($endpoint, null);
            if (!empty($productId)) {
                \StockAvailable::setQuantity($productId, $combiId, $model->getStockLevel());
            }
        }

        return $model;
    }
}
