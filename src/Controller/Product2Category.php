<?php

namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Model\Product2Category as Product2CategoryModel;
use jtl\Connector\Model\Identity;

class Product2Category extends AbstractController
{
    public function pullData($data, $model, $limit = null)
    {
        $return = [];

        foreach (\Product::getProductCategories($data['id_product']) as $catId) {
            $cat = new Product2CategoryModel();
            $cat->setProductId($model->getId());
            $cat->setCategoryId(new Identity($catId));
            $cat->setId(new Identity($model->getId()->getEndpoint() . '_' . $catId));

            $return[] = $cat;
        }

        return $return;
    }

    public function pushData($data)
    {
        $id = $data->getId()->getEndpoint();

        if (!empty($id)) {
            $cats = [];

            foreach ($data->getCategories() as $category) {
                $catId = $category->getCategoryId()->getEndpoint();

                if (!empty($catId)) {
                    $cats[] = $catId;
                }
            }

            $model = new \Product($id);
            $model->updateCategories($cats);
        }
    }
}
