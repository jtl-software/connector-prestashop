<?php
namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Model\ProductStockLevel as ProductStockLevelModel;

class ProductStockLevel extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $query = 'SELECT quantity FROM '._DB_PREFIX_.'stock_available WHERE id_product='.$data['id_product'];

        if (!empty($data['id_product_attribute'])) {
            $query .= ' AND id_product_attribute = '.$data['id_product_attribute'];
        } else {
            $query .= ' AND id_product_attribute = 0';
        }

        $stock = $this->db->getValue($query);

        $stockLevel = new ProductStockLevelModel();
        $stockLevel->setProductId($model->getId());
        $stockLevel->setStockLevel(floatval($stock));

        return $stockLevel;
    }

    public function pushData($data, $model)
    {
        $id = $data->getProductId()->getEndpoint();

        if (!empty($id)) {
            if (strpos($id, '_') === false) {
                \StockAvailable::setQuantity($id, null, $data->getStockLevel());
                \StockAvailable::setProductOutOfStock($id, $model->out_of_stock == 1 ? true : false);
            } else {
                list($productId, $combiId) = explode('_',$id);

                if (!empty($productId) && !empty($combiId)) {
                    \StockAvailable::setQuantity($productId, $combiId, $data->getStockLevel());
                }
            }
        }

        return $data;
    }
}
