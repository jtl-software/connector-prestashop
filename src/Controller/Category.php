<?php

namespace jtl\Connector\Presta\Controller;

class Category extends BaseController
{
    private static $idCache = [];

    public function pullData($data, $model, $limit = null)
    {
        $result = $this->db->executeS(
            '
			SELECT c.* 
			FROM ' . \_DB_PREFIX_ . 'category c
			LEFT JOIN jtl_connector_link_category l ON c.id_category = l.endpoint_id
            WHERE l.host_id IS NULL AND c.id_parent != 0 AND c.is_root_category = 0
            ORDER BY c.nleft
            LIMIT ' . $limit
        );

        $return = [];

        foreach ($result as $data) {
            $model = $this->mapper->toHost($data);

            $return[] = $model;
        }

        return $return;
    }

    public function pushData($data)
    {
        if (isset(static::$idCache[$data->getParentCategoryId()->getHost()])) {
            $data->getParentCategoryId()->setEndpoint(static::$idCache[$data->getParentCategoryId()->getHost()]);
        }

        $category = $this->mapper->toEndpoint($data);
        $category->save();

        $id = $category->id;

        $data->getId()->setEndpoint($id);

        static::$idCache[$data->getId()->getHost()] = $id;

        return $data;
    }

    public function deleteData($data)
    {
        $category = new \Category($data->getId()->getEndpoint());

        if (!$category->delete()) {
            //throw new \Exception('Error deleting category with id: '.$data->getId()->getEndpoint());
        }

        return $data;
    }

    public function getStats()
    {
        return $this->db->getValue(
            '
			SELECT COUNT(*) 
			FROM ' . \_DB_PREFIX_ . 'category c
			LEFT JOIN jtl_connector_link_category l ON c.id_category = l.endpoint_id
            WHERE l.host_id IS NULL AND c.id_parent != 0 AND c.is_root_category = 0
        '
        );
    }
}
