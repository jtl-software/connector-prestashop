<?php

namespace jtl\Connector\Presta\Controller;

class DeliveryNote extends BaseController
{
    public function pushData($data)
    {
        $orderId = $data->getCustomerOrderId()->getEndpoint();

        if (!empty($orderId)) {
            $trackingIds = implode(', ', $data->getTrackingLists()[0]->getCodes());
            
            $this->db->execute(sprintf('UPDATE %sorder_carrier SET tracking_number=%s WHERE id_order=%s',
                _DB_PREFIX_,
                $trackingIds,
                $orderId
            ));
        }

        return $data;
    }
}
