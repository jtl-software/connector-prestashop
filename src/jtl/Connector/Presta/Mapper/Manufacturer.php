<?php
namespace jtl\Connector\Presta\Mapper;

class Manufacturer extends BaseMapper
{
	protected $endpointModel = '\Manufacturer';

	protected $pull = array(
		'id' => 'id_manufacturer',
		'name' => 'name',
		'i18ns' => 'ManufacturerI18n'
	);

	protected $push = array(
        'id' => 'id',
        'id_manufacturer' => 'id',
		'name' => 'name',
		'ManufacturerI18n' => 'i18ns'
	);
}
