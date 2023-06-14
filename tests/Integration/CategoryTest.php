<?php

namespace Tests;

class CategoryTest extends \ConnectorIntegrationTests\Integration\CategoryTest
{
    public function getIgnoreArray()
    {
        return [
            'level',
            'id',
            'i18ns'
        ];
    }
}
