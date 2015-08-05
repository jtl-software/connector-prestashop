<?php
namespace jtl\Connector\Presta\Mapper;

class CustomerOrderBillingAddress extends BaseMapper
{
    protected $pull = array(
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
        'street' => 'address1',
        'zipCode' => 'postcode'
    );

    protected function salutation($data)
    {
        return $data['id_gender'] === '1' ? 'm' : 'w';
    }
}
