<?php

namespace jtl\Connector\Presta\Controller;

class Manufacturer extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $result = $this->db->executeS(
            '
			SELECT m.*
			FROM ' . \_DB_PREFIX_ . 'manufacturer m
			LEFT JOIN jtl_connector_link_manufacturer l ON m.id_manufacturer = l.endpoint_id
            WHERE l.host_id IS NULL
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
        $manufacturer = $this->mapper->toEndpoint($data);

        $manufacturer->name = \str_replace('#', '', $manufacturer->name);

        $manufacturer->save();

        $id = $manufacturer->id;

        $data->getId()->setEndpoint($id);

        return $data;
    }

    public function deleteData($data)
    {
        $manufacturer = new \Manufacturer($data->getId()->getEndpoint());

        if (!$manufacturer->delete()) {
            //throw new \Exception('Error deleting manufacturer with id: '.$data->getId()->getEndpoint());
        }

        return $data;
    }

    public function getStats()
    {
        return $this->db->getValue(
            '
			SELECT COUNT(*) 
			FROM ' . \_DB_PREFIX_ . 'manufacturer m
			LEFT JOIN jtl_connector_link_manufacturer l ON m.id_manufacturer = l.endpoint_id
            WHERE l.host_id IS NULL
        '
        );
    }
}
