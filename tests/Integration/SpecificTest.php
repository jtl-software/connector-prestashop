<?php


namespace Tests;


class SpecificTest extends \Jtl\Connector\IntegrationTests\Integration\SpecificTest
{
    public function getIgnoreArray()
    {
        return [
            'isGlobal',
            'values.0.i18ns.0.description',
            'values.0.i18ns.0.metaDescription',
            'values.0.i18ns.0.metaKeywords',
            'values.0.i18ns.0.titleTag',
            'values.0.i18ns.0.urlPath',
        ];
    }
    
    public function testSpecificBasicPush()
    {
        $this->expectException("TypeError");
        parent::testSpecificBasicPush();
    }
}
