<?php

namespace jtl\Connector\Presta\Mapper;

class CustomerOrderBillingAddress extends BaseMapper
{
    protected $pull = [
        'id' => 'id_address',
        'customerId' => 'id_customer',
        'city' => 'city',
        'company' => 'company',
        'countryIso' => 'countryIso',
        'eMail' => 'email',
        'extraAddressLine' => 'address2',
        'firstName' => 'firstname',
        'lastName' => 'lastname',
        'mobile' => 'phone_mobile',
        'phone' => 'phone',
        'salutation' => null,
        'state' => 'state',
        'vatNumber' => 'vat_number',
        'street' => 'address1',
        'zipCode' => 'postcode'
    ];

    protected function salutation($data)
    {
        $mappings = ['1' => 'm', '2' => 'w'];
        if (isset($mappings[$data['id_gender']])) {
            return $mappings[$data['id_gender']];
        }
        return '';
    }
}
