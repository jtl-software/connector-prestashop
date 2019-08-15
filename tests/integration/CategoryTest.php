<?php


namespace Tests;


class CategoryTest extends CategoryTest
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
