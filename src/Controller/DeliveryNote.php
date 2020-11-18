<?php

namespace jtl\Connector\Presta\Controller;

class DeliveryNote extends BaseController
{
    public function pushData($data)
    {
        $orderId = $data->getCustomerOrderId()->getEndpoint();

        if (!empty($orderId)) {
            $trackingCodes = [];
            foreach ($data->getTrackingLists() as $trackingList) {
                $trackingCodes = array_merge($trackingCodes, $trackingList->getCodes());
            }

            $codesString = implode(', ', $trackingCodes);

            if (count($trackingCodes) > 0) {
                $this->db->execute(sprintf('UPDATE %sorder_carrier SET tracking_number=
                    IF(LENGTH(tracking_number) > 0, CONCAT_WS(", ", tracking_number, "%s"), "%s") WHERE id_order=%s',
                    _DB_PREFIX_,
                    $codesString,
                    $codesString,
                    $orderId
                ));
            }
        }

        return $data;
    }
}


