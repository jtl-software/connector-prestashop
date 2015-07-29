<?php
namespace jtl\Connector\Presta\Controller;

class CategoryInvisibility extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $result = $this->db->executeS('
            SELECT g.id_group
            FROM ps_group g
            WHERE NOT EXISTS(
              SELECT *
              FROM ps_category_group i
              WHERE g.id_group = i.id_group AND i.id_category = '.$data['id_category'].'
            )');

        $return = array();

        foreach ($result as $dataInv) {
            $dataInv['id_category'] = $data['id_category'];
            $model = $this->mapper->toHost($dataInv);

            $return[] = $model;
        }

        return $return;
    }
}
