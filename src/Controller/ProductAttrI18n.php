<?php

namespace jtl\Connector\Presta\Controller;

class ProductAttrI18n extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $resultA = $this->db->executeS(
            '
			SELECT fl.*
			FROM '._DB_PREFIX_.'feature_lang fl
			LEFT JOIN '._DB_PREFIX_.'lang AS l ON l.id_lang = fl.id_lang
			WHERE l.id_lang IS NOT NULL AND fl.id_feature = '.$data['id_feature']
        );

        $resultV = $this->db->executeS(
            '
			SELECT vl.*
			FROM '._DB_PREFIX_.'feature_value_lang vl
			LEFT JOIN '._DB_PREFIX_.'lang AS l ON l.id_lang = vl.id_lang
			WHERE l.id_lang IS NOT NULL AND vl.id_feature_value = '.$data['id_feature_value']
        );

        $return = [];
        $i18ns = [];

        foreach ($resultA as $aData) {
            $i18ns[$aData['id_lang']] = $aData;
        }

        foreach ($resultV as $vData) {
            if (isset($i18ns[$vData['id_lang']])) {
                $i18ns[$vData['id_lang']]['value'] = $vData['value'];
            }
        }

        foreach ($i18ns as $i18n) {
            $model = $this->mapper->toHost($i18n);

            $return[] = $model;
        }

        return $return;
    }
}
