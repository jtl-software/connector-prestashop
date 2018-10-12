<?php
namespace jtl\Connector\Presta\Mapper;

class Specific extends BaseMapper
{
    protected $pull = array(
        'id' => 'id_feature',
        'i18ns' => 'SpecificI18n',
        'values' => 'SpecificValue',
    );
}
