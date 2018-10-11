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
        $this->removeCurrentAttributes($model);
        
        foreach ($data->getAttributes() as $attr) {
            if ($attr->getIsCustomProperty() === false || \Configuration::get('jtlconnector_custom_fields')) {
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
    
    protected function removeCurrentAttributes($model)
    {
        $attributes = $this->db->ExecuteS('
        SELECT p.*, f.*
		FROM `'._DB_PREFIX_.'feature_product` as p
		LEFT JOIN `'._DB_PREFIX_.'feature_value` as f ON (f.`id_feature_value` = p.`id_feature_value`)
		WHERE `id_product` = '.intval($model->id).' AND `custom` = 1;
        ');
        
        if (!empty($attributes)) {
            foreach ($attributes as $attr) {
                $this->db->Execute('
                DELETE FROM `'._DB_PREFIX_.'feature_value`
                WHERE `id_feature_value` = '.intval($attr['id_feature_value']));
                $this->db->Execute('
                DELETE FROM `'._DB_PREFIX_.'feature_value_lang`
                WHERE `id_feature_value` = '.intval($attr['id_feature_value']));
                $this->db->Execute('
                DELETE FROM `'._DB_PREFIX_.'feature_product`
                WHERE `id_product` = '.intval($model->id).' AND `id_feature_value` = '.intval($attr['id_feature_value']));
            }
        }
    }
}
