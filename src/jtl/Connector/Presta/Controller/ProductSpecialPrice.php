<?php
namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Model\Identity;
use jtl\Connector\Model\ProductSpecialPrice as ProductSpecialPriceModel;
use jtl\Connector\Model\ProductSpecialPriceItem as ProductSpecialPriceItemModel;
use jtl\Connector\Presta\Utils\Utils;

class ProductSpecialPrice extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        /*
        $return = array();

        $productTaxRate = Utils::getInstance()->getProductTaxRate($data['id_product']);

        $pResult = $this->db->executeS('
			SELECT p.*, pr.price AS pPrice
			FROM '._DB_PREFIX_.'specific_price p
			LEFT JOIN '._DB_PREFIX_.'product pr ON pr.id_product = p.id_product
			WHERE
			    p.id_product_attribute = 0
			    AND p.id_product = '.$data['id_product'].'
			    AND p.id_country = 0
			    AND p.id_currency = 0
			    AND id_customer = 0
			    AND p.from != "0000-00-00 00:00:00"
                AND p.from_quantity = 1
        ');

        $varResult = array();

        if (isset($data['id_product_attribute'])) {
            $varResult = $this->db->executeS('
                SELECT p.*, pr.price AS pPrice
                FROM '._DB_PREFIX_.'specific_price p
                LEFT JOIN '._DB_PREFIX_.'product pr ON pr.id_product = p.id_product
                WHERE
                    p.id_product_attribute = '.$data['id_product_attribute'].'
                    AND p.id_country = 0
			        AND p.id_currency = 0
			        AND id_customer = 0
			        AND p.from != "0000-00-00 00:00:00"
                    AND p.from_quantity = 1
            ');
        }

        $result = array_merge($pResult, $varResult);

        $groupPrices = array();

        foreach ($result as $pData) {
            if ($pData['id_customer'] !== '0') {
                //$customerPrices[$pData['id_customer']][] = $pData;
            } elseif ($pData['id_group'] !== '0') {
                $groupPrices[$pData['id_group']][] = $pData;
            } else {
                foreach (\Group::getGroups(1) as $gData) {
                    $groupPrices[$gData['id_group']][] = $pData;
                }
            }
        }

        foreach ($groupPrices as $gId => $gPriceData) {
            if ($gId === 0) $gId = '';
            $gPrice = new ProductSpecialPriceModel();
            $gPrice->setId(new Identity($model->getId()->getEndpoint().'_g'.$gId));
            $gPrice->setProductId($model->getId());

            foreach ($gPriceData as $gItemData) {
                $gPrice->setActiveFromDate(new \DateTime($gItemData['from']));

                $gItem = new ProductSpecialPriceItemModel();
                $gItem->setProductSpecialPriceId($gPrice->getId());
                $gItem->setCustomerGroupId(new Identity($gId));
                $gItem->setPriceNet($this->calculateNetPrice($gItemData, $productTaxRate));

                $gPrice->addItem($gItem);
            }

            $return[] = $gPrice;
        }

        return $return;
        */
    }

    public function pushData($data, $model = null)
    {
        $id = $data->getId()->getEndpoint();

        if (!empty($id)) {
            list($productId, $combiId) = explode('_', $id);

            if (is_null($combiId)) $combiId = 0;

            if (!empty($productId) && !is_null($combiId)) {
                $this->db->execute('
                    DELETE p FROM '._DB_PREFIX_.'specific_price p
                    WHERE p.id_product = '.$productId.'
                    AND p.id_product_attribute = '.$combiId.'
                    AND p.from != "0000-00-00 00:00:00"
                ');

                foreach ($data->getSpecialPrices() as $specialPrice) {
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
                        $priceObj->from = $specialPrice->getActiveFromDate()->format('Y-m-d H:i:s');
                        $priceObj->to = $specialPrice->getActiveUntilDate()->format('Y-m-d H:i:s');

                        $priceObj->save();
                    }
                }
            }
        }

        return $price;
    }

    /*
    private function calculateNetPrice($data, $taxRate)
    {
        if ($data['price'] === '-1.000000') {
            if ($data['reduction_type'] === 'amount') {
                $reduction = ($data['reduction_tax'] === 1) ? ($data['reduction'] / (100 + $taxRate)) * 100 : $data['reduction'];
                return floatval($data['pPrice'] - $reduction);
            } else {
                return floatval($data['pPrice'] - (($data['pPrice'] / 100) * $data['price']));
            }
        } else {
            return floatval($data['price']);
        }
    }
    */
}
