<?php
namespace jtl\Connector\Presta\Controller;

class CustomerOrderItem extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $result = $this->db->executeS('
			SELECT i.*
			FROM '._DB_PREFIX_.'order_detail i
			WHERE i.id_order = '.$data['id_order']
        );

        $return = array();

        foreach ($result as $iData) {
            $model = $this->mapper->toHost($iData);

            $return[] = $model;
        }

        return $return;
    }
}
