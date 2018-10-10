<?php
namespace jtl\Connector\Presta\Controller;

class DeliveryNote extends BaseController
{
    public function pushData($data)
    {
        $orderId = $data->getCustomerOrderId()->getEndpoint();

        if (!empty($orderId)) {
            foreach ($data->getTrackingLists() as $list) {
                foreach (\Carrier::getCarriers(null, true) as $carrier) {
                    if ($list->getName() == $carrier['name']) {
                        $this->db->execute('UPDATE '._DB_PREFIX_.'order_carrier SET tracking_number="'.implode(', ', $list->getCodes()).'" WHERE id_order='.$orderId);
                        break;
                    }
                }
            }
        }

        return $data;
    }
}
