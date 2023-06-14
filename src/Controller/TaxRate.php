<?php

namespace jtl\Connector\Presta\Controller;

class TaxRate extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $rates = $this->db->executeS('
          SELECT r.id_tax, t.rate
          FROM ' . \_DB_PREFIX_ . 'tax_rule r
          LEFT JOIN ' . \_DB_PREFIX_ . 'tax t ON t.id_tax=r.id_tax
          WHERE r.id_country=' . \Context::getContext()->country->id . ' AND t.active = 1
          GROUP BY r.id_tax
        ');

        foreach ($rates as $rate) {
            $model = $this->mapper->toHost($rate);

            $return[] = $model;
        }

        return $return;
    }
}
