<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Controller;

use Jtl\Connector\Core\Controller\PushInterface;
use Jtl\Connector\Core\Model\AbstractModel;
use Jtl\Connector\Core\Model\DeliveryNote;
use jtl\Connector\Presta\Utils\QueryBuilder;

class DeliveryNoteController extends AbstractController implements PushInterface
{
    /**
     * @param AbstractModel $deliveryNote
     *
     * @return DeliveryNote
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function push(AbstractModel $deliveryNote): AbstractModel
    {

        /** @var DeliveryNote $deliveryNote */
        $prestaOrder = new \Order((int) $deliveryNote->getCustomerOrderId()->getEndpoint());

        if (!$prestaOrder->id) {
            $this->logger->error(
                \sprintf(
                    "Order with id %s not found",
                    $deliveryNote->getCustomerOrderId()->getEndpoint()
                )
            );
            throw new \Exception(
                \sprintf("Order with id %s not found", $deliveryNote->getCustomerOrderId()->getEndpoint())
            );
        }

        $qb                 = new QueryBuilder();
        $sql                = $qb->select('id_order_carrier')
                                ->from('order_carrier')
                                ->where('id_order = ' . (int)$prestaOrder->id);
        $prestaOrderCarrier = $this->db->getValue($sql->build());

        if (!$prestaOrderCarrier) {
            $this->logger->error(
                \sprintf(
                    "Order carrier for order %s not found",
                    $prestaOrder->id
                )
            );
            throw new \Exception(
                \sprintf("Order carrier for order %s not found", $prestaOrder->id)
            );
        }

        $prestaCarrier = new \OrderCarrier((int)$prestaOrderCarrier);

        $trackingCodes = [];
        foreach ($deliveryNote->getTrackingLists() as $trackingList) {
            foreach ($trackingList->getCodes() as $code) {
                foreach ($trackingList->getTrackingURLs() as $trackingUrl) {
                    #if tracking url is sent, use url, otherwise use tracking code
                    $trackingCodes[] = str_contains($trackingUrl, $code) ? $trackingUrl : $code;
                }
            }
        }

        if (!empty($trackingCodes)) {
            if (!empty($prestaCarrier->tracking_number)) {
                $trackingCodes = \array_merge(
                    $trackingCodes,
                    \array_map('trim', \explode(',', $prestaCarrier->tracking_number))
                );
            }

            $codes = \implode(',', \array_unique($trackingCodes));

            $prestaCarrier->tracking_number = $codes;


            try {
                if (!$prestaCarrier->update()) {
                    $this->logger->error(
                        \sprintf(
                            "Couldn't update delivery note for order %s",
                            $prestaOrder->id
                        ),
                        [
                            "order" => $prestaOrder
                        ]
                    );
                    throw new \Exception(\sprintf("Couldn't update delivery note for order %s", $prestaOrder->id));
                }
            } catch (\Exception $e) {
                if (\str_contains($e->getMessage(), 'tracking_number')) {
                    $this->logger->error(
                        \sprintf(
                            "Prestashop does not like the tracking number \"%s\", verify that it is correct",
                            $codes
                        ),
                        [
                            "exception" => $e,
                            "order"     => $prestaOrder
                        ]
                    );

                    throw new \Exception(
                        \sprintf(
                            "Prestashop does not like the "
                            . "tracking number \"%s\" for order %s, verify that it is correct"
                            . " | Presta Error: %s",
                            $codes,
                            $prestaOrder->id,
                            $e->getMessage()
                        )
                    );
                }
                throw $e;
            }
        }

        return $deliveryNote;
    }
}
