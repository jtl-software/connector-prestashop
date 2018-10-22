<?php

namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Model\Identity;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use jtl\Connector\Model\Product as ProductModel;
use jtl\Connector\Model\ProductSpecific as ProductSpecificModel;


class ProductSpecific extends BaseController
{
    public function pullData(ProductModel $product)
    {
        $specifics = $this->db->executeS($test = sprintf('
            SELECT fp.id_feature, fp.id_product, fp.id_feature_value
            FROM `%sfeature_product` fp
            LEFT JOIN `%sfeature_value` fv ON (fp.id_feature_value = fv.id_feature_value)
            WHERE custom = 0 AND `id_product` = %s',
            _DB_PREFIX_, _DB_PREFIX_, $product->getId()->getEndpoint()));
        
        $productSpecifics = [];
        
        foreach ($specifics as $value) {
            $productSpecific = new ProductSpecificModel();
            $productSpecific->setId(new Identity($value['id_feature']))
                ->setProductId(new Identity($product->getId()->getEndpoint()))
                ->setSpecificValueId(new Identity($value['id_feature_value']));
            
            $productSpecifics[] = $productSpecific;
        }
        
        return $productSpecifics;
    }
    
    public function pushData(ProductModel $product, \Product $endpointProduct)
    {
        $currentValues = [];
        foreach ($product->getSpecifics() as $productSpecific) {
            if ($productSpecific->getSpecificValueId()->getEndpoint() > 0) {
                $currentValues[] = $productSpecific->getSpecificValueId()->getEndpoint();
                
                if (!$this->linkingExists($productSpecific)) {
                    $this->createLinking($productSpecific, $endpointProduct);
                }
            }
        }
        
        $this->unlinkOldSpecificValues($product, $currentValues);
    }
    
    protected function createLinking(ProductSpecificModel $productSpecific, \Product $product)
    {
        if ($productSpecific->getId()->getEndpoint() && $productSpecific->getSpecificValueId()->getEndpoint()) {
            return $this->db->insert('feature_product',
                [
                    'id_feature'       => $productSpecific->getId()->getEndpoint(),
                    'id_product'       => $product->id,
                    'id_feature_value' => $productSpecific->getSpecificValueId()->getEndpoint(),
                ]);
        }
        
        return false;
    }
    
    protected function linkingExists(ProductSpecificModel $productSpecific)
    {
        return (int)$this->db->getValue(sprintf('
            SELECT COUNT(*)
            FROM %sfeature_product
            WHERE id_feature = %s AND id_product = %s AND id_feature_value = %s',
            _DB_PREFIX_,
            $productSpecific->getId()->getEndpoint(),
            $productSpecific->getProductId()->getEndpoint(),
            $productSpecific->getSpecificValueId()->getEndpoint()
        ));
    }
    
    protected function unlinkOldSpecificValues(ProductModel $product, $existingSpecificValues = [])
    {
        $specificValuesToRemove = $this->db->executeS(sprintf('
            SELECT id_feature_value, id_product
            FROM %sfeature_product
            WHERE id_product = %s AND id_feature_value NOT IN (%s) AND id_feature_value NOT IN (
                SELECT id_feature_value
                FROM %sfeature_value
                WHERE custom = 1
            )',
            _DB_PREFIX_,
            $product->getId()->getEndpoint(),
            implode(',', array_merge($existingSpecificValues, [0])),
            _DB_PREFIX_
        )
        );
        
        if (is_array($specificValuesToRemove)) {
            foreach ($specificValuesToRemove as $value) {
                $this->db->Execute(sprintf('
                    DELETE FROM `%sfeature_product`
                    WHERE `id_feature_value` = %s AND `id_product` = %s',
                        _DB_PREFIX_,
                        $value['id_feature_value'],
                        $value['id_product'])
                );
            }
        }
    }
}
