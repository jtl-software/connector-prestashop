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

		return $return;
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
                    $data->getMinimumOrderQuantity()
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
                    $data->getMinimumOrderQuantity()
                );

                $id = $data->getMasterProductId()->getEndpoint().'_'.$combiId;
            }

            $combi = new \Combination($combiId);

            $valIds = array();

            foreach ($data->getVariations() as $variation) {
                $attrNames = array();
                foreach ($variation->getI18ns() as $varI18n) {
                    $langId = Utils::getInstance()->getLanguageIdByIso($varI18n->getLanguageISO());

                    $attrNames[$langId] = $varI18n->getName();

                    if ($langId == \Context::getContext()->country->id) {
                        $attrGrpId = $this->db->getValue('SELECT id_attribute_group FROM '._DB_PREFIX_.'attribute_group_lang WHERE name="'.$varI18n->getName().'"');
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

                        $valNames[$langId] = $valI18n->getName();

                        if ($langId == \Context::getContext()->country->id) {
                            $valId = $this->db->getValue('
                              SELECT l.id_attribute
                              FROM '._DB_PREFIX_.'attribute_lang l
                              LEFT JOIN '._DB_PREFIX_.'attribute a ON a.id_attribute = l.id_attribute
                              WHERE l.name="'.$valI18n->getName().'" && a.id_attribute_group = '.$attrGrpId
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

            $combi->setAttributes($valIds);
            $combi->save();
        }

		$data->getId()->setEndpoint($id);

        if($id) {
            $data->getStockLevel()->getProductId()->setEndpoint($id);
            $stock = new ProductStockLevel();
            $stock->pushData($data->getStockLevel());

            foreach ($data->getPrices() as $price) {
                $price->getProductId()->setEndpoint($id);
            }

            $price = new ProductPrice();
            $price->pushData($data->getPrices());

            $categories = new Product2Category();
            $categories->pushData($data);
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
