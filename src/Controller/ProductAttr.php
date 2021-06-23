<?php

namespace jtl\Connector\Presta\Controller;

use Configuration;
use Context;
use Exception;
use jtl\Connector\Presta\Utils\Utils;
use PrestaShopDatabaseException;
use PrestaShopException;

class ProductAttr extends BaseController
{
    public const
        DELIVERY_OUT_STOCK = 'delivery_out_stock',
        DELIVERY_IN_STOCK = 'delivery_in_stock',
        AVAILABLE_LATER = 'available_later',
        TAGS = 'tags';

    /**
     * @var array<string>
     */
    protected static $specialAttributes = [
        'online_only' => 'online_only',
        'products_status' => 'active',
        'main_category_id' => 'id_category_default',
    ];

    /**
     * @var array<string>
     */
    protected static $i18nAttributes = [
        self::DELIVERY_OUT_STOCK,
        self::DELIVERY_IN_STOCK,
        self::AVAILABLE_LATER,
    ];

    /**
     * @param $data
     * @param \jtl\Connector\Model\Product $model
     * @param int $limit
     * @return array
     * @throws PrestaShopDatabaseException
     */
    public function pullData($data, $model, $limit = null)
    {
        $productId = $model->getId()->getEndpoint();

        $attributes = $this->db->executeS(sprintf(
            '
            SELECT fp.id_feature, fp.id_product, fp.id_feature_value
            FROM `%sfeature_product` fp
            LEFT JOIN `%sfeature_value` fv ON (fp.id_feature_value = fv.id_feature_value)
            WHERE custom = 1 AND `id_product` = "%s"',
            _DB_PREFIX_,
            _DB_PREFIX_,
            $productId
        ));

        $return = [];

        foreach ($attributes as $attribute) {
            $model = $this->mapper->toHost($attribute);

            $return[] = $model;
        }

        return $return;
    }

    /**
     * @param \jtl\Connector\Model\Product $data
     * @param \Product $model
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function pushData($data, $model)
    {
        $attributesToIgnore = self::getAttributesToIgnore();

        $this->removeCurrentAttributes($model);
        foreach ($data->getAttributes() as $attr) {
            $isIgnoredAttribute = false;
            if ($attr->getIsCustomProperty() === false || Configuration::get('jtlconnector_custom_fields')) {
                $featureData = [];


                foreach ($attr->getI18ns() as $i18n) {
                    $name = array_search($i18n->getName(), $attributesToIgnore);
                    if ($name === false) {
                        $name = $i18n->getName();
                    }

                    if (isset($attributesToIgnore[$name])) {
                        $isIgnoredAttribute = true;
                        break;
                    }

                    $id = Utils::getInstance()->getLanguageIdByIso($i18n->getLanguageISO());

                    if (is_null($id)) {
                        $id = Context::getContext()->language->id;
                    }

                    $name = $i18n->getName();
                    if (!empty($name)) {
                        $featureData['names'][$id] = $name;
                        $featureData['values'][$id] = $i18n->getValue();
                    }

                    if ($id == Context::getContext()->language->id) {
                        $fId = $this->db->getValue(sprintf(
                            '
                        SELECT id_feature
                        FROM %sfeature_lang
                        WHERE name = "%s"
                        GROUP BY id_feature',
                            _DB_PREFIX_,
                            $name
                        ));
                    }
                }

                if ($isIgnoredAttribute || !isset($featureData['names'])) {
                    continue;
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

    /**
     * @param $model
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws Exception
     */
    protected function removeCurrentAttributes($model)
    {
        $attributeIds = $this->db->executeS(sprintf(
            '
			SELECT id_feature
			FROM %sfeature_value
            WHERE custom = 1 AND id_feature IN (
                SELECT id_feature
                FROM %sfeature_product
                WHERE id_product = %s
                GROUP BY id_feature
            )
            GROUP BY id_feature',
            _DB_PREFIX_,
            _DB_PREFIX_,
            $model->id
        ));

        if (!is_array($attributeIds)) {
            return;
        }

        foreach ($attributeIds as $attributeId) {
            if ($this->isSpecific($attributeId['id_feature'])) {
                $attributeValues = $this->db->executeS(sprintf(
                    '
                    SELECT id_feature_value
                    FROM %sfeature_value
                    WHERE custom = 1 AND id_feature = %s',
                    _DB_PREFIX_,
                    $attributeId['id_feature']
                ));
                foreach ($attributeValues as $attributeValue) {
                    $this->db->Execute(sprintf(
                        '
                        DELETE FROM `%sfeature_value`
                        WHERE `id_feature_value` = %s',
                        _DB_PREFIX_,
                        intval($attributeValue['id_feature_value'])
                    ));
                    $this->db->Execute(sprintf(
                        '
                        DELETE FROM `%sfeature_value_lang`
                        WHERE `id_feature_value` = %s',
                        _DB_PREFIX_,
                        intval($attributeValue['id_feature_value'])
                    ));
                    $this->db->Execute(sprintf(
                        '
                        DELETE FROM `%sfeature_product`
                        WHERE `id_product` = %s AND `id_feature_value` = %s',
                        _DB_PREFIX_,
                        intval($model->id),
                        intval($attributeValue['id_feature_value'])
                    ));
                }
            } else {
                $feature = new \Feature($attributeId['id_feature']);
                if (!$feature->delete()) {
                    throw new Exception('Error deleting attribute with id: ' . $attributeId['id_feature']);
                }
            }
        }
    }

    /**
     * @param $attributeId
     * @return boolean
     */
    protected function isSpecific($attributeId)
    {
        if (!$attributeId) {
            return false;
        }

        return (bool)$this->db->getValue(sprintf(
            '
            SELECT COUNT(*)
            FROM %sfeature_value
            WHERE custom = 0 AND id_feature = %s',
            _DB_PREFIX_,
            $attributeId
        ));
    }

    /**
     * @return array<string>
     */
    public static function getSpecialAttributes(): array
    {
        return self::$specialAttributes;
    }

    /**
     * @return array<string>
     */
    public static function getI18nAttributes(): array
    {
        return self::$i18nAttributes;
    }

    /**
     * @return array<string>
     */
    public function getAttributesToIgnore(): array
    {
        return array_merge(self::$specialAttributes, array_combine(array_values(self::$i18nAttributes), array_values(self::$i18nAttributes)), [self::TAGS => self::TAGS]);
    }
}
