<?php
namespace jtl\Connector\Presta\Mapper;

class SpecificI18n extends ProductI18n
{
    protected $pull = array(
        'specificId' => 'id_feature',
        'languageISO' => null,
        'name' => 'name'/*
        'value' => 'value'*/
    );
}
