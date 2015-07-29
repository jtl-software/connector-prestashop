<?php
namespace jtl\Connector\Presta\Checksum;

use \jtl\Connector\Checksum\IChecksumLoader;
use \jtl\Connector\Core\Database\Mysql;

class ChecksumLoader implements IChecksumLoader
{
    public function read($endpointId, $type)
    {
    }

    public function delete($endpointId, $type)
    {
    }

    public function write($endpointId, $type, $checksum)
    {
    }
}
