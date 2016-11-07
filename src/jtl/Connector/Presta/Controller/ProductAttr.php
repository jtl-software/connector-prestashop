<?php
namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Presta\Utils\Utils;

class ProductAttr extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $productId = $model->getId()->getEndpoint();

        $result = $this->db->executeS('
			SELECT p.*
			FROM '._DB_PREFIX_.'feature_product p
			WHERE p.id_product= '.$data['id_product']
        );

        $return = array();

        foreach ($result as $lData) {
            $lData['id_product'] = $productId;
            $model = $this->mapper->toHost($lData);

            $return[] = $model;
        }

        return $return;
    }

    public function pushData($data, $model)
    {
        $model->deleteFeatures();

        foreach ($data->getAttributes() as $attr) {
            if ($attr->getIsCustomProperty() === false) {
                $featureData = array();

                foreach ($attr->getI18ns() as $i18n) {
                    $id = Utils::getInstance()->getLanguageIdByIso($i18n->getLanguageISO());

                    if (is_null($id)) {
                        $id = \Context::getContext()->language->id;
                    }

                    $name = $i18n->getName();
                    if (!empty($name)) {
                        $featureData['names'][$id] = $name;
                        $featureData['values'][$id] = $i18n->getValue();
                    }

                    if ($id == \Context::getContext()->language->id) {
                        $fId = $this->db->getValue('
                        SELECT id_feature
                        FROM ' . _DB_PREFIX_ . 'feature_lang
                        WHERE name = "' . $name . '"
                        GROUP BY id_feature
                    ');
                    }
                }

                $feature = new \Feature($fId);

                foreach ($featureData['names'] as $lang => $fName) {
                    $feature->name[$lang] = $fName;
                }

                $feature->save();

                if (!empty($feature->id)) {
                    $valueId = $model->addFeaturesToDB($feature->id, null, true);

                    if (!empty($valueId)) {
                        foreach ($featureData['values'] as $lang => $fValue) {
                            $model->addFeaturesCustomToDB($valueId, $lang, $fValue);
                        }
                    }
                }
            }
        }
    }
}
