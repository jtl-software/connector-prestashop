<?php

namespace jtl\Connector\Presta\Controller;

use Attribute;
use AttributeGroup;
use Combination;
use Context;
use Exception;
use jtl\Connector\Model\Identity;
use jtl\Connector\Presta\Utils\Utils;
use PrestaShopDatabaseException;
use PrestaShopException;

class Product extends BaseController
{
    private static $idCache = [];

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
            $this->db->execute('UPDATE ' . _DB_PREFIX_ . 'product SET unit_price_ratio=' . $data->getBasePriceDivisor() . ' WHERE id_product=' . $data->getId()->getEndpoint());
            $this->db->execute('UPDATE ' . _DB_PREFIX_ . 'product_shop SET unit_price_ratio=' . $data->getBasePriceDivisor() . ' WHERE id_product=' . $data->getId()->getEndpoint());
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
        if (isset(static::$idCache[$data->getMasterProductId()->getHost()])) {
            $data->getMasterProductId()->setEndpoint(static::$idCache[$data->getMasterProductId()->getHost()]);
        }

        $masterId = $data->getMasterProductId()->getEndpoint();

        if (empty($masterId)) {
            $product = $this->mapper->toEndpoint($data);

            $product->save();

            $id = $product->id;
        } else {
            list($productId, $combiId) = Utils::explodeProductEndpoint($data->getId()->getEndpoint());

            $product = new \Product($masterId);

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

            $valIds = [];
            $attrGrpId = null;

            foreach ($data->getVariations() as $variation) {
                $attrNames = [];
                foreach ($variation->getI18ns() as $varI18n) {
                    $langId = Utils::getInstance()->getLanguageIdByIso($varI18n->getLanguageISO());

                    $varName = $varI18n->getName();

                    if (!empty($varName)) {
                        $attrNames[$langId] = $varName;
                    }

                    if ($langId == Context::getContext()->language->id) {
                        $attrGrpId = $this->db->getValue('SELECT id_attribute_group FROM ' . _DB_PREFIX_ . 'attribute_group_lang WHERE name="' . $varName . '"');
                    }
                }

                $attrGrp = new AttributeGroup($attrGrpId);
                $attrGrp->name = $attrNames;
                $attrGrp->public_name = $attrNames;
                $attrGrp->group_type = 'select';

                $attrGrp->save();

                $attrGrpId = $attrGrp->id;

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

            $combi->price = 0;
            $combi->setAttributes($valIds);
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
        $isCombi = (strpos($data->getId()->getEndpoint(), '_') === false) ? false : true;

        if (!$isCombi) {
            $obj = new \Product($data->getId()->getEndpoint());
        } else {
            list($productId, $combiId) = explode('_', $data->getId()->getEndpoint());

            $obj = new Combination($combiId);
        }

        if (!$obj->delete()) {
            throw new Exception('Error deleting product with id: ' . $data->getId()->getEndpoint());
        }

        return $data;
    }

    public function getStats()
    {
        $count = $this->db->getValue('
			SELECT COUNT(*) 
			FROM ' . _DB_PREFIX_ . 'product p
			LEFT JOIN jtl_connector_link_product l ON CAST(p.id_product AS CHAR) = l.endpoint_id
            WHERE l.host_id IS NULL AND p.id_product > 0
        ');

        $countVars = $this->db->getValue('
            SELECT COUNT(*)
            FROM ' . _DB_PREFIX_ . 'product_attribute p
			LEFT JOIN jtl_connector_link_product l ON CONCAT(p.id_product, "_", p.id_product_attribute) = l.endpoint_id
            WHERE l.host_id IS NULL AND p.id_product > 0
        ');

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
        foreach ($data->getAttributes() as $attribute) {
            foreach ($attribute->getI18ns() as $i18n) {
                $name = array_search($i18n->getName(), $specialAttributes);
                if ($name === false) {
                    $name = $i18n->getName();
                }

                if (isset($specialAttributes[$name]) && $i18n->getValue() !== "") {
                    $foundSpecialAttributes[$name] = $i18n->getValue();
                    break;
                }
            }
        }

        foreach ($foundSpecialAttributes as $key => $value) {
            if ($value === 'false' || $value === 'true') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif (is_numeric($value)) {
                $value = (int)$value;
                if ($key === 'main_category_id') {
                    $found = false;
                    foreach ($data->getCategories() as $product2Category) {
                        if ($product2Category->getCategoryId()->getHost() === $value) {
                            $value = (int)$product2Category->getCategoryId()->getEndpoint();
                            $found = true;
                            break;
                        }
                    }

                    if(!$found) {
                        continue;
                    }
                }
            }

            $product->{$specialAttributes[$key]} = $value;
        }

        $prices = $data->getPrices();
        $product->price = round(end($prices)->getItems()[0]->getNetPrice(), 6);
        $product->save();
    }

    /**
     * @param $data
     * @param \jtl\Connector\Model\Product $model
     */
    private function pullSpecialAttributes($data, $model)
    {
        foreach (ProductAttr::getSpecialAttributes() as $wawiName => $prestaName) {
            $attribute = new \jtl\Connector\Model\ProductAttr();
            $attributeI18n = new \jtl\Connector\Model\ProductAttrI18n();
            $attribute->setId(new Identity($prestaName));
            $attribute->setProductId($model->getId());
            $attributeI18n->setProductAttrId($attribute->getId());
            $attributeI18n->setLanguageISO(Utils::getInstance()->getLanguageIsoById((string)Context::getContext()->language->id));
            $attributeI18n->setName($wawiName);
            $attributeI18n->setValue($data[$prestaName]);
            $attribute->setI18ns([$attributeI18n]);
            $model->addAttribute($attribute);
        }
    }
}
