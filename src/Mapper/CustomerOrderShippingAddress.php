<?php

namespace jtl\Connector\Presta\Mapper;

class CustomerOrderShippingAddress extends BaseMapper
{
    protected array $pull = [
        'id'               => 'id_address',
        'customerId'       => 'id_customer',
        'city'             => 'city',
        'company'          => 'company',
        'countryIso'       => 'countryIso',
        'eMail'            => 'email',
        'extraAddressLine' => 'address2',
        'firstName'        => 'firstname',
        'lastName'         => 'lastname',
        'mobile'           => 'phone_mobile',
        'phone'            => 'phone',
        'salutation'       => null,
        'state'            => 'state',
        'street'           => 'address1',
        'zipCode'          => 'postcode'
    ];

    protected function salutation($data): string
    {
        $mappings = ['1' => 'm', '2' => 'w'];
        if (isset($mappings[$data['id_gender']])) {
            return $mappings[$data['id_gender']];
        }
        return '';
    }
}
