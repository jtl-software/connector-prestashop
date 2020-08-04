<?php

namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Model\CustomerOrder;

class StatusChange extends BaseController
{
    public function pushData($status)
    {
        $orderId = $status->getCustomerOrderId()->getEndpoint();

        if (!empty($orderId)) {
            $newStatus = null;

            if ($status->getOrderStatus() == CustomerOrder::STATUS_CANCELLED) {
                $newStatus = 6;
            } else {
                if ($status->getPaymentStatus() == CustomerOrder::PAYMENT_STATUS_COMPLETED && $status->getOrderStatus() == CustomerOrder::STATUS_SHIPPED) {
                    $newStatus = 4;
                } else {
                    if ($status->getOrderStatus() == CustomerOrder::STATUS_SHIPPED) {
                        $newStatus = 4;
                    } elseif ($status->getPaymentStatus() == CustomerOrder::PAYMENT_STATUS_COMPLETED) {
                        $newStatus = 2;
                    }
                }
            }

            if (!is_null($newStatus)) {
                $order = new \Order($orderId);
                if ($order->getCurrentState() != $newStatus) {
                    $order->setCurrentState($newStatus);
                }
            }
        }

        return $status;
    }
}
