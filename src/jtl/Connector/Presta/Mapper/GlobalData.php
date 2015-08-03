<?php
namespace jtl\Connector\Presta\Mapper;

class GlobalData extends BaseMapper
{
	protected $pull = array(
		'languages' => 'Language',
		'currencies' => 'Currency',
		'taxRates' => 'TaxRate',
        'customerGroups' => 'CustomerGroup'
	);
}
