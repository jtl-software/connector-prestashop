<?php
namespace jtl\Connector\Presta\Mapper;

class Language extends BaseMapper
{
	protected $pull = array(
		'id' => 'id_lang',
		'isDefault' => null,
		'languageISO' => 'iso3',
		'nameEnglish' => 'name',
		'nameGerman' => 'name'
	);

	protected function isDefault($data)
	{
		return $data['id_lang'] === '1' ? true : false;
	}
}
