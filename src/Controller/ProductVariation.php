<?php

namespace jtl\Connector\Presta\Controller;

class ProductVariation extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $masterId = $model->getMasterProductId()->getEndpoint();
        
        $where = 'p.id_product = ' . $data['id_product'];
        
        if (!empty($masterId)) {
            $where = 'p.id_product_attribute = ' . $data['id_product_attribute'];
        }
        
        $result = $this->db->executeS('
			SELECT a.*, g.position AS groupPos, p.ean13, p.isbn, p.reference
            FROM ' . _DB_PREFIX_ . 'product_attribute p
            LEFT JOIN ' . _DB_PREFIX_ . 'product_attribute_combination c ON c.id_product_attribute = p.id_product_attribute
            LEFT JOIN ' . _DB_PREFIX_ . 'attribute a ON a.id_attribute = c.id_attribute
            LEFT JOIN ' . _DB_PREFIX_ . 'attribute_group g ON g.id_attribute_group = a.id_attribute_group
            WHERE ' . $where . '
            GROUP BY a.id_attribute
        ');
        
        $vars = [];
        $return = [];
        
        foreach ($result as $varRData) {
            $vars[$varRData['id_attribute_group']]['id'] = $varRData['id_attribute_group'];
            $vars[$varRData['id_attribute_group']]['productId'] = $model->getId()->getEndpoint();
            $vars[$varRData['id_attribute_group']]['sort'] = $varRData['groupPos'];
            $vars[$varRData['id_attribute_group']]['values'][] = [
                'id'                 => $varRData['id_attribute'],
                'sort'               => $varRData['position'],
                'productVariationId' => $varRData['id_attribute_group'],
                'ean'                => $varRData['ean13'],
                'sku'                => $varRData['reference'],
            ];
        }
        
        foreach ($vars as $varId => $varData) {
            $model = $this->mapper->toHost($varData);
            
            $return[] = $model;
        }
        
        return $return;
    }
}
