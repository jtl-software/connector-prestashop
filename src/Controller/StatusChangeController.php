<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Controller;

use Jtl\Connector\Core\Controller\PushInterface;
use Jtl\Connector\Core\Model\AbstractModel;
use Jtl\Connector\Core\Model\CustomerOrder;

class StatusChangeController extends AbstractController implements PushInterface
{
    /**
     * @param AbstractModel $model
     * @return AbstractModel
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function push(AbstractModel $model): AbstractModel
    {
        $orderId = $model->getCustomerOrderId()->getEndpoint();

        if (!empty($orderId)) {
            $newStatus = match (true) {
                $model->getOrderStatus() === CustomerOrder::STATUS_CANCELLED => 6,
                $model->getPaymentStatus() === CustomerOrder::PAYMENT_STATUS_COMPLETED
                && $model->getOrderStatus() === CustomerOrder::STATUS_SHIPPED => 4,
                $model->getOrderStatus() === CustomerOrder::STATUS_SHIPPED => 4,
                $model->getPaymentStatus() === CustomerOrder::PAYMENT_STATUS_COMPLETED => 2,
                default => null
            };

            if (!\is_null($newStatus)) {
                $order = new \Order($orderId);
                if ($order->getCurrentState() != $newStatus) {
                    $order->setCurrentState($newStatus);
                }
            }
        }

        return $model;
    }
}
