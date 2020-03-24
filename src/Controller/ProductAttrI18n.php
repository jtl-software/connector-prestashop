<?php
namespace jtl\Connector\Presta\Controller;

class ProductAttrI18n extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $resultA = $this->db->executeS('
			SELECT l.*
			FROM '._DB_PREFIX_.'feature_lang l
			WHERE l.id_feature = '.$data['id_feature']
        );

        $resultV = $this->db->executeS('
			SELECT l.*
			FROM '._DB_PREFIX_.'feature_value_lang l
			WHERE l.id_feature_value = '.$data['id_feature_value']
        );

        $return = array();
        $i18ns = array();

        foreach ($resultA as $aData) {
            $i18ns[$aData['id_lang']] = $aData;
        }

        foreach ($resultV as $vData) {
            $i18ns[$vData['id_lang']]['value'] = $vData['value'];
        }

        foreach ($i18ns as $i18n) {
            $model = $this->mapper->toHost($i18n);

            $return[] = $model;
        }

        return $return;
    }
}
