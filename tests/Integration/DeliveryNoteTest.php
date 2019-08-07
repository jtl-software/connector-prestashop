<?php

namespace Tests\Integration;

use DateTime;
use jtl\Connector\Model\DeliveryNote;
use jtl\Connector\Model\DeliveryNoteItem;
use jtl\Connector\Model\DeliveryNoteItemInfo;
use jtl\Connector\Model\DeliveryNoteTrackingList;
use jtl\Connector\Model\Identity;
use Tests\PrestashopConnectorTestCase;

class DeliveryNoteTest extends PrestashopConnectorTestCase
{
    public function testDeliveryNoteBasicPush()
    {
        $deliveryNote = new DeliveryNote();
        $deliveryNote->setCustomerOrderId(new Identity('', 1));
        $deliveryNote->setId(new Identity('', 1));
        $deliveryNote->setCreationDate(new DateTime());
        $deliveryNote->setIsFulfillment(true);
        $deliveryNote->setNote('');
        
        $this->pushCoreModels([$deliveryNote], true);
    }
    
    public function testDeliveryNoteItemsPush()
    {
        $deliveryNote = new DeliveryNote();
            $item = new DeliveryNoteItem();
            $item->setCustomerOrderItemId(new Identity('', 1));
            $item->setDeliveryNoteId(new Identity('', 1));
            $item->setProductId(new Identity('', 1));
            $item->setId(new Identity('', 1));
            $item->setQuantity(0.0);
                $info = new DeliveryNoteItemInfo();
                $info->setBatch('');
                $info->setBestBefore(new DateTime());
                $info->setQuantity(0.0);
                $info->setWarehouseId(0);
            $item->addInfo($info);
        $deliveryNote->addItem($item);
        
        $this->pushCoreModels([$deliveryNote], true);
    }
    
    public function testDeliveryNoteTrackingListsPush()
    {
        $deliveryNote = new DeliveryNote();
            $trackingList = new DeliveryNoteTrackingList();
            $trackingList->setName('');
            $trackingList->addCode('');
        $deliveryNote->addTrackingList($trackingList);
        
        $this->pushCoreModels([$deliveryNote], true);
    }
}
