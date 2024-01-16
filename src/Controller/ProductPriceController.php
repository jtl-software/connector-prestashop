<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Controller;

use Jtl\Connector\Core\Controller\PushInterface;
use Jtl\Connector\Core\Model\AbstractModel;
use jtl\Connector\Presta\Utils\QueryBuilder;
use jtl\Connector\Presta\Utils\Utils;
use Jtl\Connector\Core\Model\Product as JtlProduct;
use Product as PrestaProduct;
use Jtl\Connector\Core\Model\ProductPriceItem as JtlProductPriceItem;

class ProductPriceController extends AbstractController implements PushInterface
{
    /**
     * @param AbstractModel $model
     * @return AbstractModel
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function push(AbstractModel $model): AbstractModel
    {
        /** @var JtlProduct $model */
        $endpoint = $model->getId()->getEndpoint();

        if (!empty($endpoint)) {
            list($productId, $combiId) = Utils::explodeProductEndpoint($endpoint, 0);

            foreach ($model->getPrices() as $price) {
                if (!empty($productId) && !\is_null($combiId)) {
                    $customerGroupId = (int)$price->getCustomerGroupId()->getEndpoint();
                    $this->handlePrices((int)$productId, (int)$combiId, $customerGroupId, ...$price->getItems());
                }
            }
        }
        return $model;
    }

    /**
     * @param int $productId
     * @param int $combiId
     * @param int $groupId
     * @return \SpecificPrice
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function findSpecificPrice(int $productId, int $combiId, int $groupId): \SpecificPrice
    {
        $sql = (new QueryBuilder())
            ->select('id_specific_price')
            ->from('specific_price')
            ->where("id_product = $productId AND id_product_attribute = $combiId AND id_group = $groupId");

        $id = $this->db->getValue($sql);

        return new \SpecificPrice($id > 0 ? $id : null);
    }

    /**
     * @param \SpecificPrice $price
     * @param JtlProductPriceItem $priceItem
     * @param int $productId
     * @param int $combiId
     * @param int $groupId
     * @return void
     * @throws \PrestaShopException
     */
    protected function updateSpecificPrice(
        \SpecificPrice      $price,
        JtlProductPriceItem $priceItem,
        int                 $productId,
        int                 $combiId,
        int                 $groupId
    ): void {
        $price->id_product           = $productId;
        $price->id_product_attribute = $combiId;
        $price->id_group             = $groupId;
        $price->price                = \round($priceItem->getNetPrice(), 6);
        $price->from_quantity        = $priceItem->getQuantity();
        $price->id_shop              = 0;
        $price->id_currency          = 0;
        $price->id_country           = 0;
        $price->id_customer          = 0;
        $price->reduction            = 0;
        $price->reduction_type       = 'amount';
        $price->from                 = '0000-00-00 00:00:00';
        $price->to                   = '0000-00-00 00:00:00';

        $price->save();
    }

    /**
     * @param JtlProductPriceItem $priceItem
     * @param int $productId
     * @param int $combiId
     * @return void
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function createDefaultPrice(JtlProductPriceItem $priceItem, int $productId, int $combiId): void
    {
        $product = new PrestaProduct($productId);
        if (empty($combiId)) {
            $product->price = \round($priceItem->getNetprice(), 6);
            $product->save();
        } else {
            $combiPriceDiff = $priceItem->getNetPrice() - \floatval($product->price);
            $combi          = new \Combination($combiId);
            $combi->price   = \round($combiPriceDiff, 6);
            $combi->save();
        }
    }

    /**
     * @param int $productId
     * @param int $combiId
     * @param int $groupId
     * @param JtlProductPriceItem ...$priceItems
     * @return void
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function handlePrices(
        int                 $productId,
        int                 $combiId,
        int                 $groupId,
        JtlProductPriceItem ...$priceItems
    ): void {
        foreach ($priceItems as $jtlPriceItem) {
            if (empty($groupId)) {
                $this->createDefaultPrice($jtlPriceItem, $productId, $combiId);
            } else {
                $this->updateSpecificPrice(
                    $this->findSpecificPrice($productId, $combiId, $groupId),
                    $jtlPriceItem,
                    $productId,
                    $combiId,
                    $groupId
                );
            }
        }
    }
}
