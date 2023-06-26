<?php

namespace jtl\Connector\Presta\Controller;

use Attribute;
use AttributeGroup;
use Combination;
use Context;
use Exception;
use jtl\Connector\Model\Identity;
use jtl\Connector\Model\ProductAttrI18n as ProductAttrI18nModel;
use jtl\Connector\Model\ProductAttr as ProductAttrModel;
use jtl\Connector\Model\Product as ProductModel;
use jtl\Connector\Presta\Mapper\ProductAttr as ProductAttrMapper;
use jtl\Connector\Presta\Utils\Utils;
use jtl\Connector\Model\ProductVariation;
use PrestaShopDatabaseException;
use PrestaShopException;

class Product extends BaseController
{
    private static $idCache = [];

    protected $productAttrMapper;

    /**
     * Product constructor.
     */
    public function __construct()
    {
        $this->productAttrMapper = new ProductAttrMapper();
        parent::__construct();
    }

    public function pullData($data, $model, $limit = null)
    {
        //$limit = $limit < 25 ? $limit : 25;

        $return = [];

        $result = $this->db->executeS(
            '
			SELECT * FROM ' . _DB_PREFIX_ . 'product p
			LEFT JOIN jtl_connector_link_product l ON CAST(p.id_product AS CHAR) = l.endpoint_id
            WHERE l.host_id IS NULL AND p.id_product > 0
            LIMIT ' . $limit
        );

        $count = 0;

        foreach ($result as $data) {
            $carriers = $this->db->executeS(
                '
                SELECT id_carrier_reference
                FROM ' . _DB_PREFIX_ . 'product_carrier
                WHERE id_product = ' . $data['id_product'] . '
                    AND id_shop = ' . $data['id_shop_default']
            );

            $carrierIds = [];

            foreach ($carriers as $carrier) {
                $carrierIds[] = $carrier['id_carrier_reference'];
            }

            $data['carriers'] = implode(',', $carrierIds);

            $model = $this->mapper->toHost($data);

            $return[] = $model;
            $this->pullSpecialAttributes($data, $model);

            $count++;
        }

        if ($count < $limit) {
            $resultVars = $this->db->executeS(
                '
                SELECT p.*, pr.price AS pPrice FROM ' . _DB_PREFIX_ . 'product_attribute p
                LEFT JOIN ' . _DB_PREFIX_ . 'product pr ON pr.id_product = p.id_product
                LEFT JOIN jtl_connector_link_product l ON CONCAT(p.id_product, "_", p.id_product_attribute) = l.endpoint_id
                WHERE l.host_id IS NULL AND p.id_product > 0
                LIMIT ' . ($limit - $count)
            );

            foreach ($resultVars as $data) {
                $model = $this->mapper->toHost($data);

                $return[] = $model;
            }
        }

        //Adding Specifics to products
        /** @var \jtl\Connector\Model\Product $product */
        foreach ($return as $product) {
            $product->setSpecifics(ProductSpecific::getInstance()->pullData($product));
        }

        return $return;
    }

    public function postPush($data)
    {
        $masterId = $data->getMasterProductId()->getEndpoint();

        if (empty($masterId)) {
            $this->db->execute(
                'UPDATE ' . _DB_PREFIX_ . 'product SET unit_price_ratio=' . $data->getBasePriceDivisor(
                ) . ' WHERE id_product=' . $data->getId()->getEndpoint()
            );
            $this->db->execute(
                'UPDATE ' . _DB_PREFIX_ . 'product_shop SET unit_price_ratio=' . $data->getBasePriceDivisor(
                ) . ' WHERE id_product=' . $data->getId()->getEndpoint()
            );
        }

        \Product::flushPriceCache();
    }

    /**
     * @param \jtl\Connector\Model\Product $data
     * @return mixed
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function pushData($data)
    {
        /** @var \Product $product */

        if (isset(static::$idCache[$data->getMasterProductId()->getHost()])) {
            $data->getMasterProductId()->setEndpoint(static::$idCache[$data->getMasterProductId()->getHost()]);
        }

        $masterId = $data->getMasterProductId()->getEndpoint();

        $exists = false;

        try {
            if (empty($masterId)) {
                $product = $this->mapper->toEndpoint($data);

                $exists = $product->id > 0;

                $product->save();

                $id = $product->id;
            } else {
                list($productId, $combiId) = Utils::explodeProductEndpoint($data->getId()->getEndpoint());

                $product = new \Product($masterId);

                $exists = $product->id > 0;

                $minOrder = ceil($data->getMinimumOrderQuantity());
                $minOrder = $minOrder < 1 ? 1 : $minOrder;

                if (!empty($combiId)) {
                    $product->updateAttribute(
                        $combiId,
                        null,
                        null,
                        $data->getShippingWeight(),
                        null,
                        null,
                        null,
                        $data->getSku(),
                        $data->getEan(),
                        null,
                        null,
                        $data->getUpc(),
                        $minOrder
                    );

                    $id = $data->getId()->getEndpoint();
                } else {
                    $combiId = $product->addAttribute(
                        null,
                        $data->getShippingWeight(),
                        null,
                        null,
                        null,
                        $data->getSku(),
                        $data->getEan(),
                        null,
                        null,
                        $data->getUpc(),
                        $minOrder
                    );

                    $id = $data->getMasterProductId()->getEndpoint() . '_' . $combiId;
                }

                $combi = new Combination($combiId);

                $allowedGroupTypes = [
                    ProductVariation::TYPE_RADIO,
                    ProductVariation::TYPE_SELECT
                ];

                $valIds = [];
                foreach ($data->getVariations() as $variation) {
                    $groupType = in_array(
                        $variation->getType(),
                        $allowedGroupTypes
                    ) ? $variation->getType() : ProductVariation::TYPE_SELECT;
                    $attrGrpId = null;
                    $attrPublicNames = [];
                    $attrNames = [];
                    foreach ($variation->getI18ns() as $varI18n) {
                        $langId = Utils::getInstance()->getLanguageIdByIso($varI18n->getLanguageISO());
                        $varName = $varI18n->getName();
                        if (!empty($varName)) {
                            $attrNames[$langId] = sprintf('%s (%s)', $varName, ucfirst($groupType));
                            $attrPublicNames[$langId] = $varName;
                        }

                        if ($langId == Context::getContext()->language->id) {
                            $sql = sprintf(
                                'SELECT id_attribute_group FROM %sattribute_group_lang WHERE name = "%s"',
                                _DB_PREFIX_,
                                $attrNames[$langId]
                            );
                            $attrGrpId = $this->db->getValue($sql);
                        }
                    }

                    if (in_array($attrGrpId, [false, null], true) || in_array(
                            $variation->getType(),
                            $allowedGroupTypes
                        )) {
                        $attrGrp = new AttributeGroup($attrGrpId);
                        $attrGrp->name = $attrNames;
                        $attrGrp->public_name = $attrPublicNames;
                        $attrGrp->group_type = $groupType;
                        $attrGrp->save();
                        $attrGrpId = $attrGrp->id;
                    }

                    foreach ($variation->getValues() as $value) {
                        $valNames = [];
                        foreach ($value->getI18ns() as $valI18n) {
                            $langId = Utils::getInstance()->getLanguageIdByIso($valI18n->getLanguageISO());

                            $valName = $valI18n->getName();

                            if (!empty($valName)) {
                                $valNames[$langId] = $valName;
                            }

                            if ($langId == Context::getContext()->language->id) {
                                $valId = $this->db->getValue(
                                    '
                              SELECT l.id_attribute
                              FROM ' . _DB_PREFIX_ . 'attribute_lang l
                              LEFT JOIN ' . _DB_PREFIX_ . 'attribute a ON a.id_attribute = l.id_attribute
                              WHERE l.name="' . $valName . '" && a.id_attribute_group = ' . $attrGrpId
                                );
                            }
                        }

                        $val = new Attribute($valId);
                        $val->name = $valNames;
                        $val->id_attribute_group = $attrGrpId;
                        $val->position = $value->getSort();

                        $val->save();

                        $valId = $val->id;

                        $valIds[] = $valId;
                    }
                }

                $oldVariantImages = $combi->getWsImages();
                $combi->price = 0;
                $combi->setAttributes($valIds);
                $combi->setWsImages($oldVariantImages);
                $combi->save();

                $product->checkDefaultAttributes();
            }

            $data->getId()->setEndpoint($id);

            if ($id) {
                $data->getStockLevel()->getProductId()->setEndpoint($id);
                $stock = new ProductStockLevel();
                $stock->pushData($data->getStockLevel(), $product);

                foreach ($data->getPrices() as $price) {
                    $price->getProductId()->setEndpoint($id);
                }

                $price = new ProductPrice();
                foreach ($data->getPrices() as $priceData) {
                    $price->pushData($priceData);
                }

                $specialPrices = new ProductSpecialPrice();
                $specialPrices->pushData($data);

                $categories = new Product2Category();
                $categories->pushData($data);

                if (isset($product) && $data->getMasterProductId()->getHost() === 0) {
                    ProductAttr::getInstance()->pushData($data, $product);
                    ProductSpecific::getInstance()->pushData($data, $product);
                    $this->pushSpecialAttributes($data, $product);
                }
            }

            static::$idCache[$data->getId()->getHost()] = $id;

            return $data;
        } catch (\Exception $e) {
            if (!empty($product) && !$exists) {
                $product->delete();
            }
            throw $e;
        }
    }

    /**
     * @param \jtl\Connector\Model\Product $data
     * @return mixed
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     * @throws Exception
     */
    public function deleteData($data)
    {
        $endpoint = $data->getId()->getEndpoint();
        if ($endpoint !== '') {
            $isCombi = strpos($data->getId()->getEndpoint(), '_') !== false;
            if (!$isCombi) {
                $obj = new \Product($endpoint);
            } else {
                list($productId, $combiId) = explode('_', $data->getId()->getEndpoint());
                $obj = new Combination($combiId);
            }

            $obj->delete();
        }

        return $data;
    }

    public function getStats()
    {
        $count = $this->db->getValue(
            '
			SELECT COUNT(*) 
			FROM ' . _DB_PREFIX_ . 'product p
			LEFT JOIN jtl_connector_link_product l ON CAST(p.id_product AS CHAR) = l.endpoint_id
            WHERE l.host_id IS NULL AND p.id_product > 0
        '
        );

        $countVars = $this->db->getValue(
            '
            SELECT COUNT(*)
            FROM ' . _DB_PREFIX_ . 'product_attribute p
			LEFT JOIN jtl_connector_link_product l ON CONCAT(p.id_product, "_", p.id_product_attribute) = l.endpoint_id
            WHERE l.host_id IS NULL AND p.id_product > 0
        '
        );

        return ($count + $countVars);
    }

    /**
     * @param \jtl\Connector\Model\Product $data
     * @param \Product $product
     * @throws PrestaShopException
     */
    private function pushSpecialAttributes($data, $product)
    {
        /** @var \jtl\Connector\Model\Product $data */

        $specialAttributes = ProductAttr::getSpecialAttributes();

        $foundSpecialAttributes = [];
        $tags = [];
        foreach ($data->getAttributes() as $attribute) {
            foreach ($attribute->getI18ns() as $i18n) {
                if ($i18n->getName() === ProductAttr::TAGS) {
                    $id = Utils::getInstance()->getLanguageIdByIso($i18n->getLanguageISO());
                    $tags[$id] = explode(',', $i18n->getValue());
                }

                $name = array_search($i18n->getName(), $specialAttributes);
                if ($name === false) {
                    $name = $i18n->getName();
                }

                if (isset($specialAttributes[$name])) {
                    $foundSpecialAttributes[$name] = $i18n->getValue();
                    break;
                }
            }
        }

        foreach ($foundSpecialAttributes as $key => $value) {
            if ($value === 'false' || $value === 'true') {
                $value = \filter_var($value, \FILTER_VALIDATE_BOOLEAN);
            } elseif (\is_numeric($value)) {
                $value = (int)$value;
                if ($key === 'main_category_id') {
                    $found = false;
                    foreach ($data->getCategories() as $product2Category) {
                        if ($product2Category->getCategoryId()->getHost() === $value
                            && $product2Category->getCategoryId()->getEndpoint() !== '') {
                            $value = (int)$product2Category->getCategoryId()->getEndpoint();
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        continue;
                    }
                }
            }

            $product->{$specialAttributes[$key]} = $value;
        }

        \Tag::deleteTagsForProduct($product->id_product);
        foreach ($tags as $languageId => $tagList) {
            \Tag::addTags($languageId, $product->id_product, $tagList);
        }

        $prices = $data->getPrices();
        $product->price = round(end($prices)->getItems()[0]->getNetPrice(), 6);

        $rrp = $data->getRecommendedRetailPrice();
        if ($rrp > $product->price) {
            $this->saveRecommendedRetailPriceAsFeature($product, $rrp);
        }

        $product->save();

    }

    /**
     * @param \Product $prestaProduct
     * @param float $rrp
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function saveRecommendedRetailPriceAsFeature(\Product $prestaProduct, float $rrp)
    {
        $defaultLanguageId = Context::getContext()->language->id;

        $translations = [];
        foreach (Utils::getInstance()->getLanguages() as $language) {
            $translations[$language['id_lang']] = [
                'name' => ProductAttr::RECOMMENDED_RETAIL_PRICE,
                'value' => $rrp
            ];
        }

        $this->productAttrMapper->saveCustomAttribute($prestaProduct, $defaultLanguageId, $translations, true);
    }

    /**
     * @param $data
     * @param \jtl\Connector\Model\Product $model
     */
    private function pullSpecialAttributes($data, $model)
    {
        $utils = Utils::getInstance();
        $languageId = (string)Context::getContext()->language->id;
        $languageISO = $utils->getLanguageIsoById($languageId);

        foreach (ProductAttr::getSpecialAttributes() as $wawiName => $prestaName) {
            if (isset($data[$prestaName])) {
                $value = $data[$prestaName];
                if ($wawiName === 'main_category_id') {
                    $value = (string)$this->findCategoryHostIdByEndpoint((int)$value);
                }

                if ($value !== '') {
                    $this->addAttribute($wawiName, $prestaName, $model, $value, $languageISO);
                }
            }
        }

        foreach (ProductAttr::getI18nAttributes() as $attributeName) {
            $attribute = (new ProductAttrModel())
                ->setId(new Identity($attributeName))
                ->setProductId($model->getId())
                ->setIsTranslated(true);

            foreach ($this->getProductTranslations($data['id_product']) as $productTranslation) {
                if (isset($productTranslation[$attributeName]) && !empty($productTranslation[$attributeName])) {
                    $attribute->addI18n(
                        (new ProductAttrI18nModel())
                            ->setProductAttrId($attribute->getId())
                            ->setLanguageISO($utils->getLanguageIsoById($productTranslation['id_lang']))
                            ->setName($attributeName)
                            ->setValue($productTranslation[$attributeName])
                    );
                }
            }

            if (count($attribute->getI18ns()) > 0) {
                $model->addAttribute($attribute);
            }
        }

        $productTags = \Tag::getProductTags($model->getId()->getEndpoint());
        if (!empty($productTags)) {
            $productTagsAttribute = (new ProductAttrModel())
                ->setId(new Identity(ProductAttr::TAGS))
                ->setProductId($model->getId())
                ->setIsTranslated(true);

            foreach ($productTags as $languageId => $productTag) {
                $languageIso = Utils::getInstance()->getLanguageIsoById((string)$languageId);
                $productTagsAttribute->addI18n(
                    (new ProductAttrI18nModel())
                        ->setProductAttrId($productTagsAttribute->getId())
                        ->setLanguageISO($languageIso)
                        ->setName('tags')
                        ->setValue(join(',', $productTag))
                );
            }

            $model->addAttribute($productTagsAttribute);
        }
    }

    /**
     * @param string $wawiName
     * @param string $prestaName
     * @param ProductModel $model
     * @param string $value
     * @param string $languageISO
     */
    protected function addAttribute(
        string $wawiName,
        string $prestaName,
        ProductModel $model,
        string $value,
        string $languageISO
    ): void {
        $attribute = (new ProductAttrModel())
            ->setId(new Identity($wawiName))
            ->setProductId($model->getId());

        $attributeI18n = (new ProductAttrI18nModel())
            ->setProductAttrId($attribute->getId())
            ->setLanguageISO($languageISO)
            ->setName($prestaName)
            ->setValue($value);

        $attribute->addI18n($attributeI18n);

        $model->addAttribute($attribute);
    }

    /**
     * @param integer $prestaCategoryId
     * @return string
     */
    protected function findCategoryHostIdByEndpoint(int $prestaCategoryId): string
    {
        return $this->db->getValue(
            sprintf('SELECT host_id FROM jtl_connector_link_category WHERE endpoint_id = %d', $prestaCategoryId)
        );
    }

    /**
     * @param int $productId
     * @return array|null
     * @throws PrestaShopDatabaseException
     */
    protected function getProductTranslations(int $productId): array
    {
        $sql =
            'SELECT p.*' . "\n" .
            'FROM %sproduct_lang p' . "\n" .
            'WHERE p.id_product = %d';

        return $this->db->executeS(sprintf($sql, _DB_PREFIX_, $productId));
    }
}
