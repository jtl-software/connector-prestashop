<?php

namespace jtl\Connector\Presta\Mapper;

class Language extends BaseMapper
{
    protected array $pull = [
        'id'          => 'id_lang',
        'isDefault'   => null,
        'languageISO' => 'iso3',
        'nameEnglish' => 'name',
        'nameGerman'  => 'name'
    ];

    protected function isDefault($data): bool
    {
        return $data['id_lang'] === '1';
    }
}
