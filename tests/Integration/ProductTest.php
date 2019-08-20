<?php


namespace Tests;


class ProductTest extends \ConnectorIntegrationTests\Integration\ProductTest
{
    public function getIgnoreArray()
    {
        return [
            'id',
            'i18ns',
            'customerGroups',
            'invisibilities'
        ];
    }
}
