<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Jtl\Connector\Client\Client;

class ConnectorTestCase extends TestCase
{
    protected static $client = null;
    
    protected function getConnectorClient()
    {
        if (self::$client === null) {
            self::$client = $this->generateClient();
            
            return self::$client;
        }
        
        return self::$client;
    }
    
    private function generateClient()
    {
        $config = json_decode(file_get_contents('test-config.json'));
        
        return new Client($config['connector-url'], $config['connector-token']);
    }
}
