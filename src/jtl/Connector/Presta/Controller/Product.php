<?php
namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Presta\Utils\Utils;

class Product extends BaseController
{
    private static $idCache = array();

    public function pullData($data, $model, $limit = null)
	{
		$limit = $limit < 25 ? $limit : 25;

		$return = array();

        $result = $this->db->executeS('
			SELECT * FROM '._DB_PREFIX_.'product p
			LEFT JOIN jtl_connector_link l ON CAST(p.id_product AS CHAR) = l.endpointId AND l.type = 64
            WHERE l.hostId IS NULL 
            LIMIT '.$limit
        );

		$count = 0;

        foreach ($result as $data) {
			$model = $this->mapper->toHost($data);

			$return[] = $model;

			$count++;
		}

		if ($count < $limit) {
			$resultVars = $this->db->executeS('
                SELECT p.*, pr.price AS pPrice FROM ' . _DB_PREFIX_ . 'product_attribute p
                LEFT JOIN ' . _DB_PREFIX_ . 'product pr ON pr.id_product = p.id_product
                LEFT JOIN jtl_connector_link l ON CONCAT(p.id_product, "_", p.id_product_attribute) = l.endpointId AND l.type = 64
                WHERE l.hostId IS NULL
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
            $this->db->execute('UPDATE '._DB_PREFIX_.'product SET unit_price_ratio='.$data->getBasePriceDivisor().' WHERE id_product='.$data->getId()->getEndpoint());
            $this->db->execute('UPDATE '._DB_PREFIX_.'product_shop SET unit_price_ratio='.$data->getBasePriceDivisor().' WHERE id_product='.$data->getId()->getEndpoint());
        }

        \Product::flushPriceCache();
    }

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
            list($productId, $combiId) = explode('_', $data->getId()->getEndpoint());

            $product = new \Product($masterId);

            $minOrder = ceil($data->getMinimumOrderQuantity());
            $minOrder = $minOrder < 1 ? 1 : $minOrder;

            if(!empty($combiId)) {
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

                $id = $data->getMasterProductId()->getEndpoint().'_'.$combiId;
            }

            $combi = new \Combination($combiId);

            $valIds = array();

            foreach ($data->getVariations() as $variation) {
                $attrNames = array();
                foreach ($variation->getI18ns() as $varI18n) {
                    $langId = Utils::getInstance()->getLanguageIdByIso($varI18n->getLanguageISO());

                    $varName = $varI18n->getName();

                    if (!empty($varName)) {
                        $attrNames[$langId] = $varName;
                    }

                    if ($langId == \Context::getContext()->language->id) {
                        $attrGrpId = $this->db->getValue('SELECT id_attribute_group FROM '._DB_PREFIX_.'attribute_group_lang WHERE name="'.$varName.'"');
                    }
                }

                $attrGrp = new \AttributeGroup($attrGrpId);
                $attrGrp->name = $attrNames;
                $attrGrp->public_name = $attrNames;
                $attrGrp->group_type = 'select';

                $attrGrp->save();

                $attrGrpId = $attrGrp->id;

                foreach ($variation->getValues() as $value) {
                    $valNames = array();
                    foreach ($value->getI18ns() as $valI18n) {
                        $langId = Utils::getInstance()->getLanguageIdByIso($valI18n->getLanguageISO());

                        $valName = $valI18n->getName();

                        if (!empty($valName)) {
                            $valNames[$langId] = $valName;
                        }

                        if ($langId == \Context::getContext()->language->id) {
                            $valId = $this->db->getValue('
                              SELECT l.id_attribute
                              FROM '._DB_PREFIX_.'attribute_lang l
                              LEFT JOIN '._DB_PREFIX_.'attribute a ON a.id_attribute = l.id_attribute
                              WHERE l.name="'.$valName.'" && a.id_attribute_group = '.$attrGrpId
                            );
                        }
                    }

                    $val = new \Attribute($valId);
                    $val->name = $valNames;
                    $val->id_attribute_group = $attrGrpId;

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

        if($id) {
            $data->getStockLevel()->getProductId()->setEndpoint($id);
            $stock = new ProductStockLevel();
            $stock->pushData($data->getStockLevel(), $product);

            foreach ($data->getPrices() as $price) {
                $price->getProductId()->setEndpoint($id);
            }

            $price = new ProductPrice();
            $price->initPush($data->getPrices());
            foreach ($data->getPrices() as $priceData) {
                $price->pushData($priceData);
            }

            $categories = new Product2Category();
            $categories->pushData($data);

            if (isset($product) && $data->getMasterProductId()->getHost() === 0) {
                ProductAttr::getInstance()->pushData($data, $product);
                ProductSpecific::getInstance()->pushData($data, $product);
            }
        }

        static::$idCache[$data->getId()->getHost()] = $id;

		return $data;
	}

    public function deleteData($data)
    {
        $isCombi = (strpos($data->getId()->getEndpoint(), '_') === false) ? false : true;

        if (!$isCombi) {
            $obj = new \Product($data->getId()->getEndpoint());
        } else {
            list($productId, $combiId) = explode('_', $data->getId()->getEndpoint());

            $obj = new \Combination($combiId);
        }

        if (!$obj->delete()) {
            throw new \Exception('Error deleting product with id: '.$data->getId()->getEndpoint());
        }

        return $data;
    }

	public function getStats()
	{
		$count = $this->db->getValue('
			SELECT COUNT(*) 
			FROM '._DB_PREFIX_.'product p
			LEFT JOIN jtl_connector_link l ON CAST(p.id_product AS CHAR) = l.endpointId AND l.type = 64
            WHERE l.hostId IS NULL
        ');

        $countVars = $this->db->getValue('
            SELECT COUNT(*)
            FROM '._DB_PREFIX_.'product_attribute p
			LEFT JOIN jtl_connector_link l ON CONCAT(p.id_product, "_", p.id_product_attribute) = l.endpointId AND l.type = 64
            WHERE l.hostId IS NULL
        ');

        return ($count + $countVars);
	}
}
