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
        $attributeIds = $this->db->executeS('
			SELECT id_feature
			FROM ' . _DB_PREFIX_ . 'feature_value
            WHERE custom = 1 AND id_feature IN (
                SELECT id_feature
                FROM ' . _DB_PREFIX_ . 'feature_product
                WHERE id_product = ' . $model->id . '
                GROUP BY id_feature
            )
            GROUP BY id_feature
        ');

        foreach ($attributeIds as $attributeId) {
            if ($this->isSpecific($attributeId['id_feature'])) {
                $attributeValues = $this->db->executeS('
                    SELECT id_feature_value
                    FROM ' . _DB_PREFIX_ . 'feature_value
                    WHERE custom = 1 AND id_feature = ' . $attributeId['id_feature']
                );
                foreach ($attributeValues as $attributeValue) {
                    $this->db->Execute('
                        DELETE FROM `'._DB_PREFIX_.'feature_value`
                        WHERE `id_feature_value` = '.intval($attributeValue['id_feature_value']));
                    $this->db->Execute('
                        DELETE FROM `'._DB_PREFIX_.'feature_value_lang`
                        WHERE `id_feature_value` = '.intval($attributeValue['id_feature_value']));
                    $this->db->Execute('
                        DELETE FROM `'._DB_PREFIX_.'feature_product`
                        WHERE `id_product` = '.intval($model->id).' AND `id_feature_value` = '.intval($attributeValue['id_feature_value']));
                }
            } else {
                $feature = new \Feature($attributeId['id_feature']);
                if (!$feature->delete()) {
                    throw new \Exception('Error deleting attribute with id: ' . $attributeId['id_feature']);
                }
            }
        }
    }
    
    protected function isSpecific($attributeId)
    {
        return (bool)$this->db->getValue('
            SELECT COUNT(*)
            FROM ' . _DB_PREFIX_ . 'feature_value
            WHERE custom = 0 AND id_feature = ' . $attributeId
        );
    }
}
