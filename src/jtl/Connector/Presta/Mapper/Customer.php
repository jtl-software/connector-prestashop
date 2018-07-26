<?php
namespace jtl\Connector\Presta\Mapper;

use jtl\Connector\Presta\Utils\Utils;

class Customer extends BaseMapper
{
	protected $endpointModel = '\Customer';
    protected $identity = 'id|id_customer';

	protected $pull = array(
		'id' => 'cid',
		'customerGroupId' => 'id_default_group',
        'birthday' => 'birthday',
        'city' => 'city',
        'company' => 'company',
        'countryIso' => 'iso_code',
        'creationDate' => 'date_add',
        'customerNumber' => 'id_customer',
        'eMail' => 'email',
        'extraAddressLine' => 'address2',
        'firstName' => 'firstname',
        'hasCustomerAccount' => null,
        'hasNewsletterSubscription' => 'newsletter',
        'isActive' => 'active',
        'languageISO' => null,
        'lastName' => 'lastname',
        'mobile' => 'phone_mobile',
        'phone' => 'phone',
        'salutation' => null,
        'street' => 'address1',
        'vatNumber' => 'vat_number',
        'websiteUrl' => 'website',
        'zipCode' => 'postcode'
	);

	protected $push = array(
        'id' => 'id',
        'id_default_group' => 'customerGroupId',
        'birthday' => 'birthday',
        'city' => 'city',
        'company' => 'company',
        'date_add' => 'creationDate',
        'email' => 'eMail',
        'address2' => 'extraAddressLine',
        'firstname' => 'firstName',
        'newsletter' => 'hasNewsletterSubscription',
        'active' => 'isActive',
        'id_lang' => null,
        'lastname' => 'lastName',
        'phone_mobile' => 'mobile',
        'phone' => 'phone',
        'id_gender' => null,
        'address1' => 'street',
        'vat_number' => 'vatNumber',
        'website' => 'websiteUrl',
        'postcode' => 'zipCode'
    );

    protected function hasCustomerAccount($data)
    {
        return true;
    }

    protected function languageISO($data)
    {
        return Utils::getInstance()->getLanguageIsoById($data['id_lang']);
    }

    protected function salutation($data)
    {
        $mappings = ['1' => 'm', '2' => 'w'];
        if(isset($mappings[$data['id_gender']])) {
            return $mappings[$data['id_gender']];
        }
        return '';
    }

    protected function id_lang($data)
    {
        return Utils::getInstance()->getLanguageIdByIso($data->getLanguageISO());
    }

    protected function id_gender($data)
    {
        return $data->getSalutation() === 'm' ? 1 : 0;
    }
}
