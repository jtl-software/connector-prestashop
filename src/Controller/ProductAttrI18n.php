<?php

namespace jtl\Connector\Presta\Controller;

class ProductAttrI18n extends AbstractController
{
    public function pullData($data, $model, $limit = null)
    {
        $resultA = $this->getLanguageData('feature_lang', 'id_feature', (int)$data['id_feature']);
        $resultV = $this->getLanguageData('feature_value_lang', 'id_feature_value', (int)$data['id_feature_value']);

        $return = [];
        $i18ns  = [];

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
