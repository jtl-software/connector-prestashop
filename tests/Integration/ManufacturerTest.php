<?php


namespace Tests;


class ManufacturerTest extends \ConnectorIntegrationTests\Integration\ManufacturerTest
{
    public function getIgnoreArray()
    {
        return [
            'id',
            'i18ns.0.manufacturerId',
        ];
    }
}
