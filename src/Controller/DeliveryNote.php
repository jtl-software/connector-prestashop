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
                foreach ($trackingList->getCodes() as $code) {
                    foreach ($trackingList->getTrackingURLs() as $trackingUrl) {
                        #if tracking url is sent, use url, otherwise use tracking code
                        $trackingCodes = str_contains($trackingUrl, $code) ? $trackingUrl : $code;
                    }
                }
            }

            if (\count($trackingCodes) > 0) {
                $sql                   = \sprintf(
                    'SELECT tracking_number FROM %sorder_carrier WHERE id_order = %s',
                    \_DB_PREFIX_,
                    $orderId
                );
                $existingTrackingCodes = $this->db->getValue($sql);

                if (!empty($existingTrackingCodes)) {
                    $trackingCodes = \array_merge(
                        $trackingCodes,
                        \array_map('trim', \explode(',', $existingTrackingCodes))
                    );
                }

                $codesString = \implode(',', \array_unique($trackingCodes));

                $this->db->execute(
                    \sprintf(
                        'UPDATE %sorder_carrier SET tracking_number = "%s" WHERE id_order = %s',
                        \_DB_PREFIX_,
                        $codesString,
                        $orderId
                    )
                );
            }
        }

        return $data;
    }
}
