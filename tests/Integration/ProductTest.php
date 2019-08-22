<?php


namespace Tests;


class ProductTest extends \Jtl\Connector\IntegrationTests\Integration\ProductTest
{
    public function getIgnoreArray()
    {
        return [
            'i18ns',
            'customerGroups',
            'invisibilities',
            'considerStock',
            'modified',
            'permitNegativeStock',
            'minBestBeforeDate',
            'newReleaseDate',
            'nextAvailableInflowDate',
            'creationDate',
            'basePriceUnitName',
            'attributes.0',
            'attributes.1',
            'isActive',
        ];
    }
}
