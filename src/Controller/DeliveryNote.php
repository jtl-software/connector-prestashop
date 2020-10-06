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
            
            if (count($trackingCodes) > 0) {
                $this->db->execute(sprintf('UPDATE %sorder_carrier SET tracking_number=
                    CONCAT_WS(", ", IF(LENGTH(tracking_number),tracking_number, NULL), "%s") WHERE id_order=%s',
                    _DB_PREFIX_,
                    implode(', ', $trackingCodes),
                    $orderId
                ));
            }
        }
    
        return $data;
    }
}


