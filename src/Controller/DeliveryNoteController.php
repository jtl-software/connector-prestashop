<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Controller;

use Jtl\Connector\Core\Controller\PushInterface;
use Jtl\Connector\Core\Model\AbstractModel;
use Jtl\Connector\Core\Model\DeliveryNote;

class DeliveryNoteController extends AbstractController implements PushInterface
{
    /**
    * @param AbstractModel $deliveryNote
    * @return AbstractModel
    * @throws \PrestaShopDatabaseException
    * @throws \PrestaShopException
     */
    public function push(AbstractModel $deliveryNote): AbstractModel
    {
        /** @var DeliveryNote $deliveryNote */
        $prestaOrder   = new \Order($deliveryNote->getCustomerOrderId()->getEndpoint());
        $prestaCarrier = new \OrderCarrier($prestaOrder->id);

        $trackingCodes = [];
        foreach ($deliveryNote->getTrackingLists() as $trackingList) {
            $trackingCodes = \array_merge($trackingCodes, $trackingList->getCodes());
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

            if (!$prestaCarrier->update()) {
                throw new \Exception("Couldn't update delivery note for order $prestaOrder->id");
            }
        }

        return $deliveryNote;
    }
}
