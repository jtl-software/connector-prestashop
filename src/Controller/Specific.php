<?php

namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Model\Identity;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use jtl\Connector\Presta\Utils\Utils;
use jtl\Connector\Model\Specific as SpecificModel;
use jtl\Connector\Model\SpecificI18n as SpecificI18nModel;
use jtl\Connector\Model\SpecificValue as SpecificValueModel;
use jtl\Connector\Model\SpecificValueI18n as SpecificValueI18nModel;

class Specific extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $specifics = [];
        
        $specificsIds = $this->db->executeS(sprintf(
            '
			SELECT v.id_feature
			FROM %sfeature_value v
			LEFT JOIN jtl_connector_link_specific l ON v.id_feature = l.endpoint_id
            WHERE l.host_id IS NULL AND v.custom = 0
            GROUP BY v.id_feature
            LIMIT %s',
            _DB_PREFIX_,
            $limit
        ));
        
        foreach ($specificsIds as $specificsId) {
            $specific = (new SpecificModel())
                ->setIsGlobal(true)
                ->setId(new Identity($specificsId['id_feature']))
                ->setType('string');
            
            $specificI18ns = $this->db->executeS(sprintf(
                '
                SELECT *
                FROM %sfeature_lang
                WHERE id_feature = "%s"',
                _DB_PREFIX_,
                $specificsId['id_feature']
            ));
            
            foreach ($specificI18ns as $specificI18n) {
                $languageIso = Utils::getInstance()->getLanguageIsoById($specificI18n['id_lang']);
                if ($languageIso !== false) {
                    $specific->addI18n(
                        (new SpecificI18nModel)
                            ->setSpecificId($specific->getId())
                            ->setLanguageISO($languageIso)
                            ->setName((string)$specificI18n['name'])
                    );
                }
            }
            // SpecificValues
            $specificValueData = $this->db->executeS(sprintf(
                '
                SELECT *
                FROM %sfeature_value
                WHERE custom = 0 AND id_feature = %s',
                _DB_PREFIX_,
                $specificsId['id_feature']
            ));
            
            foreach ($specificValueData as $specificValueDataSet) {
                $specificValue = (new SpecificValueModel)
                    ->setId(new Identity($specificValueDataSet['id_feature_value']))
                    ->setSpecificId($specific->getId());
                
                $specificValueI18ns = $this->db->executeS(sprintf(
                    '
                    SELECT *
                    FROM %sfeature_value_lang
                    WHERE id_feature_value = %s',
                    _DB_PREFIX_,
                    $specificValueDataSet['id_feature_value']
                ));
                
                foreach ($specificValueI18ns as $specificValueI18n) {
                    $languageIso = Utils::getInstance()->getLanguageIsoById($specificValueI18n['id_lang']);
                    if ($languageIso !== false) {
                        $specificValue->addI18n((new SpecificValueI18nModel)
                            ->setLanguageISO($languageIso)
                            ->setSpecificValueId($specificValue->getId())
                            ->setValue((string)$specificValueI18n['value']));
                    }
                }
                $specific->addValue($specificValue);
            }
            $specifics[] = $specific;
        }
        
        return $specifics;
    }
    
    public function pushData(SpecificModel $specific)
    {
        $existingSpecific = $specific->getId()->getEndpoint() !== '';
        $attributeExists = $this->getAttributeExists($specific);
        //SPECIFIC
        if ($existingSpecific) {
            $feature = new \Feature($specific->getId()->getEndpoint());
        } else {
            if (is_int($attributeExists)) {
                $feature = new \Feature($attributeExists);
            } else {
                $feature = new \Feature();
            }
        }
        
        //I18N Update
        foreach ($specific->getI18ns() as $i18n) {
            $langId = (new Utils)->getLanguageIdByIso($i18n->getLanguageISO());
            if (!empty($langId)) {
                $feature->name[$langId] = $i18n->getName();
            }
        }
        
        try {
            if (!$feature->save()) {
                throw new \Exception('Error saving Specific with id: ' . $specific->getId()->getHost());
            }
        } catch (\Exception $e) {
            $specificI18ns = $specific->getI18ns();
            Logger::write(sprintf(
                '
                Error saving Specific: %s. Presta doesn\'t allow special characters in their specifics',
                reset($specificI18ns)->getName()
            ), Logger::ERROR, 'global');
            
            return $specific;
        }
        
        $specific->getId()->setEndpoint($feature->id);
        
        //SPECIFCVALUE
        $existingSpecificValues = [];
        
        foreach ($specific->getValues() as $key => $specificValue) {
            $featureValue = new \FeatureValue($specificValue->getId()->getEndpoint());
            $featureValue->id_feature = $feature->id;
            $featureValue->custom = 0;
            
            //I18N Update
            foreach ($specificValue->getI18ns() as $specificValueI18n) {
                $langId = (new Utils)->getLanguageIdByIso($specificValueI18n->getLanguageISO());
                if (!empty($langId)) {
                    $featureValue->value[$langId] = $specificValueI18n->getValue();
                }
            }
            
            try {
                if (!$featureValue->save()) {
                    throw new \Exception();
                }
            } catch (\Exception $e) {
                $specificValueI18ns = $specificValue->getI18ns();
                $specificI18ns = $specific->getI18ns();
                Logger::write(sprintf(
                    '
                Error saving SpecificValue: %s for the specific: %s. Presta doesn\'t allow special characters in their specifics_value',
                    reset($specificValueI18ns)->getValue(),
                    reset($specificI18ns)->getName()
                ), Logger::ERROR, 'global');
                continue;
            }
            
            $existingSpecificValues[] = $featureValue->id;
            $specificValue->getId()->setEndpoint($featureValue->id);
            $specificValue->getSpecificId()->setEndpoint($feature->id);
        }
        
        $this->removeOldSpecificValues($specific, $existingSpecificValues);
        
        return $specific;
    }
    
    protected function removeOldSpecificValues(SpecificModel $specific, $existingSpecificValues = [])
    {
        $specificValuesToRemove = $this->db->executeS(
            sprintf(
                '
            SELECT id_feature_value
            FROM %sfeature_value
            WHERE id_feature = %s AND custom = 0 AND id_feature_value NOT IN (%s)',
                _DB_PREFIX_,
                $specific->getId()->getEndpoint(),
                implode(',', array_merge($existingSpecificValues, [0]))
            )
        );
        
        foreach ($specificValuesToRemove as $value) {
            $this->db->Execute(
                sprintf(
                    '
                    DELETE FROM `%sfeature_value`
                    WHERE `id_feature_value` = %s',
                    _DB_PREFIX_,
                    $value['id_feature_value']
                )
            );
            $this->db->Execute(
                sprintf(
                    '
                    DELETE FROM `%sfeature_value_lang`
                    WHERE `id_feature_value` = %s',
                    _DB_PREFIX_,
                    $value['id_feature_value']
                )
            );
            (new PrimaryKeyMapper())->delete($value['id_feature_value'], null, 256);
        }
    }
    
    protected function getAttributeExists(SpecificModel $specific)
    {
        $defaultIsoId = \Context::getContext()->language->id;
        $defaultIsoCode = (new Utils)->getLanguageIsoById((string)\Context::getContext()->language->id);
        
        foreach ($specific->getI18ns() as $i18n) {
            if ($i18n->getLanguageISO() === $defaultIsoCode) {
                $sql = sprintf(
                    'SELECT id_feature
                            FROM %sfeature_lang
                            WHERE name = "%s"
                            AND id_lang = %s',
                    _DB_PREFIX_,
                    $i18n->getName(),
                    $defaultIsoId
                );
                
                $id = $this->db->getValue($sql);
                
                if ($this->isAttribute($id)) {
                    return $id;
                }
            }
        }
        
        return false;
    }
    
    protected function deleteData(SpecificModel $specific)
    {
        $specificId = (int)$specific->getId()->getEndpoint();
        
        if (!empty($specificId)) {
            $this->removeOldSpecificValues($specific);
            if (!$this->isAttribute($specificId)) {
                $feature = new \Feature($specificId);
                if (!$feature->delete()) {
                    throw new \Exception('Error deleting specific with id: ' . $specificId);
                }
            }
        }
        
        return $specific;
    }
    
    public function getStats()
    {
        return $this->db->getValue(sprintf(
            '
        SELECT COUNT(*)
        FROM (SELECT v.id_feature
              FROM %sfeature_value v
              LEFT JOIN jtl_connector_link_specific l ON v.id_feature = l.endpoint_id
              WHERE l.host_id IS NULL AND v.custom = 0
              GROUP BY v.id_feature) as Z',
            _DB_PREFIX_
        ));
    }
    
    protected function isAttribute($specificId)
    {
        if (!$specificId) {
            return false;
        }
        
        return (bool)$this->db->getValue(sprintf(
            '
            SELECT COUNT(*)
            FROM %sfeature_value
            WHERE custom = 1 AND id_feature = %s',
            _DB_PREFIX_,
            $specificId
        ));
    }
    
    public function getSpecificValues($specificId)
    {
        if (!$specificId) {
            return false;
        }
        
        return $this->db->executeS(sprintf(
            '
            SELECT *
            FROM %sfeature_value
            WHERE custom = 0 AND id_feature = %s',
            _DB_PREFIX_,
            $specificId
        ));
    }
}
