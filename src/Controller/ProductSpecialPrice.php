<?php

namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Model\Identity;
use jtl\Connector\Model\ProductSpecialPrice as ProductSpecialPriceModel;
use jtl\Connector\Model\ProductSpecialPriceItem as ProductSpecialPriceItemModel;
use jtl\Connector\Presta\Utils\Utils;

/**
 * Class ProductSpecialPrice
 * @package jtl\Connector\Presta\Controller
 */
class ProductSpecialPrice extends BaseController
{
    /**
     * @param $data
     * @param $model
     * @param null $limit
     * @return array
     * @throws \PrestaShopDatabaseException
     */
    public function pullData($data, $model, $limit = null)
    {
        $return = [];

        $productTaxRate = Utils::getInstance()->getProductTaxRate($data['id_product']);

        $pResult = $this->db->executeS('
			SELECT p.*, pr.price AS pPrice
			FROM ' . _DB_PREFIX_ . 'specific_price p
			LEFT JOIN ' . _DB_PREFIX_ . 'product pr ON pr.id_product = p.id_product
			WHERE
			    p.id_product_attribute = 0
			    AND p.id_product = ' . $data['id_product'] . '
			    AND p.id_country = 0
			    AND p.id_currency = 0
			    AND id_customer = 0
			    AND p.from != "0000-00-00 00:00:00"                
        ');

        $varResult = [];

        if (isset($data['id_product_attribute'])) {
            $varResult = $this->db->executeS('
                SELECT p.*, pr.price AS pPrice
                FROM ' . _DB_PREFIX_ . 'specific_price p
                LEFT JOIN ' . _DB_PREFIX_ . 'product pr ON pr.id_product = p.id_product
                WHERE
                    p.id_product_attribute = ' . $data['id_product_attribute'] . '
                    AND p.id_country = 0
			        AND p.id_currency = 0
			        AND id_customer = 0
			        AND p.from != "0000-00-00 00:00:00"                    
            ');
        }

        $result = array_merge($pResult, $varResult);

        $groupPrices = Utils::groupProductPrices($result);

        $gPrice = new ProductSpecialPriceModel();
        $gPrice->setId(new Identity($model->getId()->getEndpoint() . '_g'));
        $gPrice->setProductId($model->getId());
        $gPrice->setIsActive(true);

        foreach ($groupPrices as $gId => $gPriceData) {
            if ($gId === 0) {
                $gId = '';
            }

            foreach ($gPriceData as $gItemData) {
                $gItemData['pPriceGross'] = $gItemData['pPrice'] * ($productTaxRate / 100 + 1);

                if ($gItemData['from'] !== '0000-00-00 00:00:00') {
                    $gPrice->setActiveFromDate(new \DateTime($gItemData['from']));
                }
                if ($gItemData['to'] !== '0000-00-00 00:00:00') {
                    $gPrice->setActiveUntilDate(new \DateTime($gItemData['to']));
                }
                $gPrice->setStockLimit((int)$gItemData['from_quantity']);

                $gItem = new ProductSpecialPriceItemModel();
                $gItem->setProductSpecialPriceId($gPrice->getId());
                $gItem->setCustomerGroupId(new Identity($gId));
                $gItem->setPriceNet($this->calculateNetPrice($gItemData, $productTaxRate));

                $gPrice->addItem($gItem);
            }

            $return[] = $gPrice;
        }

        return $return;
    }

    public function pushData($data, $model = null)
    {
        $id = $data->getId()->getEndpoint();

        if (!empty($id)) {
            list($productId, $combiId) = Utils::explodeProductEndpoint($id, 0);

            if (!empty($productId) && !is_null($combiId)) {
                $this->db->execute('
                    DELETE p FROM ' . _DB_PREFIX_ . 'specific_price p
                    WHERE p.id_product = ' . $productId . '
                    AND p.id_product_attribute = ' . $combiId . '
                    AND p.from != "0000-00-00 00:00:00"
                ');

                foreach ($data->getSpecialPrices() as $specialPrice) {
                    if ($specialPrice->getConsiderStockLimit() === true) {
                        continue;
                    }

                    foreach ($specialPrice->getItems() as $item) {
                        $priceObj = new \SpecificPrice();
                        $priceObj->id_product = $productId;
                        $priceObj->id_product_attribute = $combiId;
                        $priceObj->id_group = $item->getCustomerGroupId()->getEndpoint();
                        $priceObj->price = round($item->getPriceNet(), 6);
                        $priceObj->from_quantity = 0;
                        $priceObj->id_shop = 0;
                        $priceObj->id_currency = 0;
                        $priceObj->id_country = 0;
                        $priceObj->id_customer = 0;
                        $priceObj->reduction = 0;
                        $priceObj->reduction_type = 'amount';

                        if ($specialPrice->getActiveFromDate() !== null) {
                            $priceObj->from = $specialPrice->getActiveFromDate()->format('Y-m-d H:i:s');
                        } else {
                            $priceObj->from = '0000-00-00 00:00:00';
                        }

                        if ($specialPrice->getActiveUntilDate() !== null) {
                            $priceObj->to = $specialPrice->getActiveUntilDate()->format('Y-m-d H:i:s');
                        } else {
                            $priceObj->to = '0000-00-00 00:00:00';
                        }

                        $priceObj->save();
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @param $data
     * @param $taxRate
     * @return float
     */
    private function calculateNetPrice($data, $taxRate)
    {
        if ($data['price'] === '-1.000000') {
            $priceNet = $data['pPrice'];
            if ($data['reduction_type'] === 'amount') {
                $reduction = $data['reduction'];
                if ((int)$data['reduction_tax'] === 1) {
                    $reduction = $data['reduction'] / ($taxRate / 100 + 1);
                }

                return (float)round($priceNet - $reduction, 6);
            } elseif ($data['reduction_type'] === 'percentage') {
                $percentage = $data['reduction'] * 100;
                $reduction = $priceNet * $percentage / 100;

                return (float)round($priceNet - $reduction, 6);
            }
        }
        return floatval($data['price']);
    }
}
