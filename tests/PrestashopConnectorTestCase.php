<?php


namespace Tests;


use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;

class PrestashopConnectorTestCase extends ConnectorTestCase
{
    public function __construct($name = null, array $data = [], $dataName = '')
    {
        self::$primaryKeyMapper = new PrimaryKeyMapper();
        parent::__construct($name, $data, $dataName);
    }
}
