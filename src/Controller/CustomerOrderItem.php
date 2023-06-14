<?php

namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Model\CustomerOrderItem as CustomerOrderItemModel;
use jtl\Connector\Model\Identity;

/**
 * Class CustomerOrderItem
 * @package jtl\Connector\Presta\Controller
 */
class CustomerOrderItem extends BaseController
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
        $customerOrderItems = [];

        $orderId = (int)$data['id_order'];

        $orderDetails = $this->fetchOrderItems('order_detail', $orderId);
        foreach ($orderDetails as $detail) {
            $customerOrderItems[] = $this->mapper->toHost($detail);
        }

        $highestVatRate = $this->getHighestVatRate(...$customerOrderItems);

        $orderCartRules = $this->fetchOrderItems('order_cart_rule', $orderId);
        foreach ($orderCartRules as $cartRule) {
            $customerOrderItems[] = $this->createDiscountItem($cartRule, $orderId, $highestVatRate);
        }

        $customerOrderItems[] = $this->createShippingItem($data);

        return $customerOrderItems;
    }

    /**
     * @param string $tableName
     * @param int $orderId
     * @return array
     * @throws \PrestaShopDatabaseException
     */
    protected function fetchOrderItems(string $tableName, int $orderId): array
    {
        return $this->db->executeS(
            \sprintf(' SELECT r.* FROM %s%s r WHERE r.id_order = %s', \_DB_PREFIX_, $tableName, $orderId)
        );
    }

    /**
     * @param CustomerOrderItemModel ...$customerOrderItems
     * @return float
     */
    protected function getHighestVatRate(CustomerOrderItemModel ...$customerOrderItems): float
    {
        return \max(
            \array_map(function (CustomerOrderItemModel $customerOrderItem) {
                return $customerOrderItem->getVat();
            }, $customerOrderItems)
        );
    }

    /**
     * @param array $cartRule
     * @param int $orderId
     * @param float $highestVatRate
     * @return CustomerOrderItemModel
     */
    protected function createDiscountItem(array $cartRule, int $orderId, float $highestVatRate): CustomerOrderItemModel
    {
        return (new CustomerOrderItemModel())
            ->setId(new Identity('rule_' . $cartRule['id_order_cart_rule']))
            ->setCustomerOrderId(new Identity($orderId))
            ->setType(CustomerOrderItemModel::TYPE_COUPON)
            ->setName($cartRule['name'])
            ->setPrice(\floatval(-$cartRule['value_tax_excl']))
            ->setPriceGross(\floatval(-$cartRule['value']))
            ->setVat($highestVatRate)
            ->setQuantity(1);
    }

    /**
     * @param array $data
     * @return CustomerOrderItemModel
     */
    protected function createShippingItem(array $data): CustomerOrderItemModel
    {
        return (new CustomerOrderItemModel())
            ->setId(new Identity('shipping_' . $data['id_order']))
            ->setCustomerOrderId(new Identity($data['id_order']))
            ->setType(CustomerOrderItemModel::TYPE_SHIPPING)
            ->setName($data['shippingMethod'])
            ->setPrice(\floatval($data['total_shipping_tax_excl']))
            ->setPriceGross(\floatval($data['total_shipping_tax_incl']))
            ->setVat(\floatval($data['carrier_tax_rate']))
            ->setQuantity(1);
    }
}
