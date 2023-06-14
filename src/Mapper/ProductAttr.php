<?php

namespace jtl\Connector\Presta\Mapper;

class ProductAttr extends BaseMapper
{
    protected $pull = [
        'id' => 'id_feature',
        'productId' => 'id_product',
        'i18ns' => 'ProductAttrI18n',
        'isTranslated' => null
    ];

    protected function isTranslated($data)
    {
        return true;
    }

    /**
     * @param \Product $product
     * @param int $defaultLanguageId
     * @param array $translations
     * @param bool $deleteInsert
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function saveCustomAttribute(\Product $product, int $defaultLanguageId, array $translations, bool $deleteInsert = false)
    {
        $defaultFeatureName = $translations[$defaultLanguageId]['name'] ?? null;
        if ($defaultFeatureName !== null) {
            $featureId = $this->getIdFeatureByName($defaultFeatureName, $defaultLanguageId);

            if ($deleteInsert && $featureId) {
                $this->db->executeS(\sprintf('DELETE FROM %sfeature_product WHERE id_feature = %s AND id_product = %s', \_DB_PREFIX_, $featureId, $product->id));
            }

            $feature = new \Feature($featureId);

            foreach ($translations as $langId => $translation) {
                $feature->name[$langId] = $translation['name'];
            }

            $feature->save();

            if (!empty($feature->id)) {
                $valueId = $product->addFeaturesToDB($feature->id, null, true);

                if (!empty($valueId)) {
                    foreach ($translations as $langId => $translation) {
                        $product->addFeaturesCustomToDB($valueId, $langId, $translation['value']);
                    }
                }
            }
        }
    }

    /**
     * @param string $featureName
     * @param int $languageId
     * @return false|string|null
     */
    public function getIdFeatureByName(string $featureName, int $languageId)
    {
        return $this->db->getValue(\sprintf('SELECT id_feature FROM %sfeature_lang WHERE name = "%s" AND id_lang = %s', \_DB_PREFIX_, $featureName, $languageId));
    }
}
