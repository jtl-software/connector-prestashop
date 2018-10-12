<?php
namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Presta\Utils\Utils;

class Specific extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $result = $this->db->executeS('
			SELECT v.id_feature
			FROM '._DB_PREFIX_.'feature_value v
			LEFT JOIN jtl_connector_link l ON v.id_feature = l.endpointId AND l.type = 128
            WHERE l.hostId IS NULL AND v.custom = 0
            GROUP BY v.id_feature
            LIMIT '.$limit
        );
        
        $return = array();
        
        foreach ($result as $lData) {
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
                    $valueId = $model->addFeaturesToDB($feature->id, null, false);
                    
                    if (!empty($valueId)) {
                        foreach ($featureData['values'] as $lang => $fValue) {
                            $model->addFeaturesCustomToDB($valueId, $lang, $fValue);
                        }
                    }
                }
            }
        }
    }
    
    public function getStats()
    {
        return $this->db->getValue('
        SELECT COUNT(*)
        FROM (SELECT v.id_feature
              FROM ps_feature_value v
              LEFT JOIN jtl_connector_link l ON v.id_feature = l.endpointId AND l.type = 128
              WHERE l.hostId IS NULL AND v.custom = 0
              GROUP BY v.id_feature) as Z
        ');
    }
}
