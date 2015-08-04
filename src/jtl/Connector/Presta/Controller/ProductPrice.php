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
        $return = array();

        $productTaxRate = Utils::getInstance()->getProductTaxRate($data['id_product']);

        $default = new ProductpriceModel();
        $default->setId(new Identity($model->getId()->getEndpoint().'_default'));
        $default->setProductId($model->getId());
        $defaultItem = new ProductPriceItemModel();
        $defaultItem->setProductPriceId($default->getId());

        if (isset($data['id_product_attribute'])) {
            $defaultItem->setNetPrice(floatval($data['pPrice'] - $data['price']));
        } else {
            $defaultItem->setNetPrice(floatval($data['price']));
        }

        $default->addItem($defaultItem);

        $return[] = $default;

        $pResult = $this->db->executeS('
			SELECT p.*, pr.price AS pPrice
			FROM '._DB_PREFIX_.'specific_price p
			LEFT JOIN '._DB_PREFIX_.'product pr ON pr.id_product = p.id_product
			WHERE
			    p.id_product_attribute = 0
			    AND p.id_product = '.$data['id_product'].'
			    AND p.id_country = 0
			    AND p.id_currency = 0
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
            ');
        }

        $result = array_merge($pResult, $varResult);

        $customerPrices = array();
        $groupPrices = array();

        foreach ($result as $pData) {
            if ($pData['id_customer'] !== '0') {
                $customerPrices[$pData['id_customer']][] = $pData;
            } elseif ($pData['id_group'] !== '0') {
                $groupPrices[$pData['id_group']][] = $pData;
            } else {
                $groupPrices[0][] = $pData;
            }
        }

        foreach ($customerPrices as $cId => $cPriceData) {
            $cPrice = new ProductpriceModel();
            $cPrice->setId(new Identity($model->getId()->getEndpoint().'_c'.$cId));
            $cPrice->setProductId($model->getId());
            $cPrice->setCustomerId(new Identity($cId));

            foreach ($cPriceData as $cItemData) {
                $cItem = new ProductPriceItemModel();
                $cItem->setProductPriceId($cPrice->getId());
                $cItem->setQuantity(intval($cItemData['from_quantity']));
                $cItem->setNetPrice($this->calculateNetPrice($cItemData, $productTaxRate));

                $cPrice->addItem($cItem);
            }

            $return[] = $cPrice;
        }

        foreach ($groupPrices as $gId => $gPriceData) {
            if ($gId === 0) $gId = '';
            $gPrice = new ProductpriceModel();
            $gPrice->setId(new Identity($model->getId()->getEndpoint().'_g'.$gId));
            $gPrice->setProductId($model->getId());
            $gPrice->setCustomerGroupId(new Identity($gId));

            foreach ($gPriceData as $gItemData) {
                $gItem = new ProductPriceItemModel();
                $gItem->setProductPriceId($gPrice->getId());
                $gItem->setQuantity(intval($gItemData['from_quantity']));
                $gItem->setNetPrice($this->calculateNetPrice($gItemData, $productTaxRate));

                $gPrice->addItem($gItem);
            }

            $return[] = $gPrice;
        }

        return $return;
    }

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
}
