<?php


namespace Tests;


class CategoryTest extends \ConnectorIntegrationTests\Integration\CategoryTest
{
    public function getIgnoreArray()
    {
        return [
            'level',
            'id',
            'parentCategoryId',
            'i18ns.0.categoryId',
            'attributes',
            'customerGroups',
            'invisibilities'
        ];
    }
}
