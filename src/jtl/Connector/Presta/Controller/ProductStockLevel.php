<?php
namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Model\ProductStockLevel as ProductStockLevelModel;

class ProductStockLevel extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $stockLevel = new ProductStockLevelModel();
        $stockLevel->setProductId($model->getId());
        $stockLevel->setStockLevel(floatval($data['quantity']));

        return $stockLevel;
    }
}
