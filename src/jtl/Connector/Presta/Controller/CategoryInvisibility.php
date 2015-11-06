<?php
namespace jtl\Connector\Presta\Controller;

class CategoryInvisibility extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $result = $this->db->executeS('
            SELECT g.id_group
            FROM ' . _DB_PREFIX_ . 'group g
            WHERE NOT EXISTS(
              SELECT *
              FROM '._DB_PREFIX_.'category_group i
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

    public function pushData($data, $model)
    {
        $invIncludes = array();
        $invExcludes = array();

        foreach ($data->getInvisibilities() as $inv) {
            $invExcludes[] = $inv->getCustomerGroupId()->getEndpoint();
        }

        foreach (\Group::getGroups(1) as $group) {
            if (!in_array($group['id_group'], $invExcludes)) {
                $invIncludes[] = $group['id_group'];
            }
        }

        $model->groupBox = $invIncludes;
    }
}
