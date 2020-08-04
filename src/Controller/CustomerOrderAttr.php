<?php

namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Model\CustomerOrderAttr as CustomerOrderAttrModel;
use jtl\Connector\Model\Identity;

class CustomerOrderAttr extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $return = [];

        if ($data['gift'] == 1) {
            $isGift = new CustomerOrderAttrModel();
            $isGift->setCustomerOrderId($model->getId());
            $isGift->setId(new Identity('gift'));
            $isGift->setKey('Geschenkverpackung');
            $isGift->setValue('Ja');

            $return[] = $isGift;

            if (!empty($data['gift_message'])) {
                $giftMessage = new CustomerOrderAttrModel();
                $giftMessage->setCustomerOrderId($model->getId());
                $giftMessage->setId(new Identity('giftMessage'));
                $giftMessage->setKey('Geschenk Nachricht');
                $giftMessage->setValue($data['gift_message']);

                $return[] = $giftMessage;
            }
        }

        return $return;
    }
}
