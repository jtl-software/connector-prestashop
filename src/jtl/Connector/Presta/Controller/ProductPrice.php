<?php
namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Model\Identity;
use jtl\Connector\Model\ProductPrice as ProductPriceModel;
use jtl\Connector\Model\ProductPriceItem as ProductPriceItemModel;

class ProductPrice extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $return = array();

        $default = new ProductpriceModel();
        $default->setId(new Identity($model->getId()->getEndpoint().'_default'));
        $default->setProductId($model->getId());
        $defaultItem = new ProductPriceItemModel();
        $defaultItem->setProductPriceId($default->getId());
        $defaultItem->setNetPrice(floatval($data['price']));
        $default->addItem($defaultItem);

        $return[] = $default;

        return $return;
    }
}
