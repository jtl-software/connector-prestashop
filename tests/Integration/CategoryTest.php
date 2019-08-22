<?php


namespace Tests;


class CategoryTest extends \Jtl\Connector\IntegrationTests\Integration\CategoryTest
{
    public function getIgnoreArray()
    {
        return [
            'level',
            'attributes',
            'customerGroups',
            'invisibilities'
        ];
    }
}
