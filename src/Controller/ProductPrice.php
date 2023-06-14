<?php

namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Model\Identity;
use jtl\Connector\Model\ProductPrice as ProductPriceModel;
use jtl\Connector\Model\ProductPriceItem as ProductPriceItemModel;
use jtl\Connector\Presta\Utils\Utils;

class ProductPrice extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $return = [];

        $productTaxRate = Utils::getInstance()->getProductTaxRate($data['id_product']);

        $default = new ProductpriceModel();
        $default->setId(new Identity($model->getId()->getEndpoint() . '_default'));
        $default->setProductId($model->getId());
        $defaultItem = new ProductPriceItemModel();
        $defaultItem->setProductPriceId($default->getId());

        if (isset($data['id_product_attribute'])) {
            $defaultItem->setNetPrice(\floatval($data['pPrice'] + $data['price']));
        } else {
            $defaultItem->setNetPrice(\floatval($data['price']));
        }

        $default->addItem($defaultItem);

        $return[] = $default;

        $pResult = $this->db->executeS('
			SELECT p.*, pr.price AS pPrice
			FROM ' . \_DB_PREFIX_ . 'specific_price p
			LEFT JOIN ' . \_DB_PREFIX_ . 'product pr ON pr.id_product = p.id_product
			WHERE
			    p.id_product_attribute = 0
			    AND p.id_product = ' . $data['id_product'] . '
			    AND p.id_country = 0
			    AND p.id_currency = 0
			    AND id_customer = 0
			    AND p.from = "0000-00-00 00:00:00"
        ');

        $varResult = [];

        if (isset($data['id_product_attribute'])) {
            $varResult = $this->db->executeS('
                SELECT p.*, pr.price AS pPrice
                FROM ' . \_DB_PREFIX_ . 'specific_price p
                LEFT JOIN ' . \_DB_PREFIX_ . 'product pr ON pr.id_product = p.id_product
                WHERE
                    p.id_product_attribute = ' . $data['id_product_attribute'] . '
                    AND p.id_country = 0
			        AND p.id_currency = 0
			        AND id_customer = 0
			        AND p.from = "0000-00-00 00:00:00"
            ');
        }

        $result = \array_merge($pResult, $varResult);

        $groupPrices = Utils::groupProductPrices($result);

        foreach ($groupPrices as $gId => $gPriceData) {
            if ($gId === 0) {
                $gId = '';
            }
            $gPrice = new ProductpriceModel();
            $gPrice->setId(new Identity($model->getId()->getEndpoint() . '_g' . $gId));
            $gPrice->setProductId($model->getId());
            $gPrice->setCustomerGroupId(new Identity($gId));

            foreach ($gPriceData as $gItemData) {
                $gItem = new ProductPriceItemModel();
                $gItem->setProductPriceId($gPrice->getId());
                $gItem->setQuantity(\intval($gItemData['from_quantity']));
                $gItem->setNetPrice($this->calculateNetPrice($gItemData, $productTaxRate));

                $gPrice->addItem($gItem);
            }

            $return[] = $gPrice;
        }

        return $return;
    }

    public function pushData($price, $model = null)
    {
        $id = $price->getProductId()->getEndpoint();

        if (!empty($id)) {
            list($productId, $combiId) = Utils::explodeProductEndpoint($id, 0);

            if (!empty($productId) && !\is_null($combiId)) {
                $customerGroupId = $price->getCustomerGroupId()->getEndpoint();

                if (!empty($customerGroupId)) {
                    $this->db->execute(\sprintf("
						DELETE p FROM %sspecific_price p
						WHERE p.id_product = %s
						AND p.id_product_attribute = %s
						AND p.from = \"0000-00-00 00:00:00\"
						AND p.id_group = %s
					", \_DB_PREFIX_, $productId, $combiId, $customerGroupId));
                }

                foreach ($price->getItems() as $item) {
                    if (empty($customerGroupId)) {
                        $product = new \Product($productId);
                        if (empty($combiId)) {
                            $product->price = \round($item->getNetprice(), 6);
                            $product->save();
                        } else {
                            $combiPriceDiff = $item->getNetPrice() - \floatval($product->price);
                            $combi          = new \Combination($combiId);
                            $combi->price   = \round($combiPriceDiff, 6);
                            $combi->save();
                        }
                    } else {
                        $priceObj                       = new \SpecificPrice();
                        $priceObj->id_product           = $productId;
                        $priceObj->id_product_attribute = $combiId;
                        $priceObj->id_group             = $customerGroupId;
                        $priceObj->price                = \round($item->getNetPrice(), 6);
                        $priceObj->from_quantity        = $item->getQuantity();
                        $priceObj->id_shop              = 0;
                        $priceObj->id_currency          = 0;
                        $priceObj->id_country           = 0;
                        $priceObj->id_customer          = 0;
                        $priceObj->reduction            = 0;
                        $priceObj->reduction_type       = 'amount';
                        $priceObj->from                 = '0000-00-00 00:00:00';
                        $priceObj->to                   = '0000-00-00 00:00:00';

                        $priceObj->save();
                    }
                }
            }
        }

        return $price;
    }

    private function calculateNetPrice($data, $taxRate)
    {
        if ($data['price'] === '-1.000000') {
            if ($data['reduction_type'] === 'amount') {
                $reduction = ($data['reduction_tax'] === 1) ? ($data['reduction'] / (100 + $taxRate)) * 100 : $data['reduction'];

                return \floatval($data['pPrice'] - $reduction);
            } else {
                return \floatval($data['pPrice'] - (($data['pPrice'] / 100) * $data['price']));
            }
        } else {
            return \floatval($data['price']);
        }
    }
}
