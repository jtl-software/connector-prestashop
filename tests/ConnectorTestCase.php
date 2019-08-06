<?php

namespace Tests;

use jtl\Connector\Linker\IdentityLinker;
use jtl\Connector\Mapper\IPrimaryKeyMapper;
use jtl\Connector\Model\DataModel;
use jtl\Connector\Serializer\JMS\SerializerBuilder;
use PHPUnit\Framework\TestCase;
use Jtl\Connector\Client\Client;

\Doctrine\Common\Annotations\AnnotationRegistry::registerLoader('class_exists');

class ConnectorTestCase extends TestCase
{
    /**
     * @var Client
     */
    protected static $client = null;
    
    /**
     * @var IPrimaryKeyMapper
     */
    protected static $primaryKeyMapper = null;
    
    /**
     * @return Client
     */
    protected function getConnectorClient()
    {
        if (self::$client === null) {
            self::$client = $this->generateClient();
            
            return self::$client;
        }
        
        return self::$client;
    }
    
    /**
     * @return Client
     */
    private function generateClient()
    {
        $config = json_decode(file_get_contents(__DIR__ . '/test-config.json'));
        
        return new Client($config->connector_token, $config->connector_url);
    }
    
    /**
     * @param string $json
     * @param string $controllerName
     * @return array|\JMS\Serializer\scalar|mixed|object
     */
    protected function jsonToCoreModels(string $json, string $controllerName)
    {
        $ns = 'ArrayCollection<jtl\\Connector\\Model\\' . $controllerName . '>';
        $serializer = SerializerBuilder::create();
        
        return $serializer->deserialize($json, $ns, 'json');
    }
    
    /**
     * @param string $modelName
     * @param int $limit
     * @param string $endpointId
     * @return DataModel|DataModel[]|null
     */
    protected function pullCoreModels(string $modelName, int $limit = 100, string $endpointId = "")
    {
        if ($endpointId === "") {
            return $this->getConnectorClient()->pull($modelName, $limit);
        }
        
        $models = $this->getConnectorClient()->pull($modelName, 999999);
        foreach ($models as $model) {
            if ($model->getId()->getEndpoint() === $endpointId) {
                return $model;
            }
        }
        
        return null;
    }
    
    /**
     * @param array $models
     * @param bool $clearLinkings
     * @return array
     * @throws \ReflectionException
     */
    protected function pushCoreModels(array $models, bool $clearLinkings)
    {
        if (empty($models)) {
            return [];
        }
        
        $controllerName = (new \ReflectionClass($models[0]))->getShortName();
        $client = $this->getConnectorClient();
        
        $convertedModels = $this->jsonToCoreModels(json_encode($client->push($controllerName, $models)),
            $controllerName);
        
        if ($clearLinkings) {
            foreach ($convertedModels as $convertedModel) {
                self::$primaryKeyMapper->delete(
                    $convertedModel->getId()->getEndpoint(),
                    $convertedModel->getId()->getHost(),
                    IdentityLinker::getInstance()->getType($controllerName, 'id')
                );
            }
        }
        
        return $convertedModels;
    }
    
    /**
     * @param DataModel $actual
     * @param DataModel $expected
     * @param array|null $assertArray
     */
    protected function assertCoreModel(DataModel $actual, DataModel $expected, array $assertArray = null)
    {
        if (empty($assertArray)) {
            $this->assertEquals($expected, $actual);
            
            return;
        }
        
        $valid = [
            'assertEquals',
            'assertSame',
        ];
        
        foreach ($assertArray as $name => $assertion) {
            $assertMethod = "assert" . ucfirst($assertion);
            $getMethod = "get" . ucfirst($name);
            
            if (array_search($assertMethod, $valid) !== false) {
                $this->$assertMethod($actual->$getMethod(), $expected->$getMethod());
            }
        }
    }
}
