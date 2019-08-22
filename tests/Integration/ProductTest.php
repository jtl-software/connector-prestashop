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
            'permitNegativeStock', //Is set default to true, otherwise products without Stock handling couldn't be bought
            'minBestBeforeDate',
            'newReleaseDate',
            'nextAvailableInflowDate',
            'creationDate',
            'basePriceUnitName',
            'attributes.0', // onlineOnly Attribute is sent by pull
            'attributes.1', // isActive Attribute is sent by pull
            'isActive', //Is Handled via attributes
        ];
    }
}
