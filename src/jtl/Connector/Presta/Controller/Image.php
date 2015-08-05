<?php
namespace jtl\Connector\Presta\Controller;

class Image extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $imgData = array_merge(
            $this->productImages(),
            $this->categoryImages(),
            $this->manufacturerImages()
        );

        $return = array();

        foreach ($imgData as $img) {
            $model = $this->mapper->toHost($img);

            $return[] = $model;
        }

        return $return;
    }

    public function pushData($data)
    {
        return $data;
    }

    public function getStats()
    {
        $imgData = array_merge(
            $this->productImages(),
            $this->categoryImages(),
            $this->manufacturerImages()
        );

        return count($imgData);
    }

    private function categoryImages()
    {
        $categories = $this->db->executeS('
          SELECT c.id_category FROM '._DB_PREFIX_.'category c
          LEFT JOIN jtl_connector_link l ON CONCAT("c", c.id_category) = l.endpointId AND l.type = 16
          WHERE l.hostId IS NULL
        ');

        $return = array();

        foreach ($categories as $category) {
            if (file_exists(_PS_CAT_IMG_DIR_.(int)$category['id_category'].'.jpg')) {
                $return[] = array(
                    'id' => 'c'.$category['id_category'],
                    'foreignKey'=> $category['id_category'],
                    'remoteUrl' => _PS_BASE_URL_._THEME_CAT_DIR_.$category['id_category'].'.jpg',
                    'filename' => $category['id_category'].'.jpg',
                    'relationType' => 'category'
                );
            }
        }

        return $return;
    }

    private function manufacturerImages()
    {
        $manufacturers = $this->db->executeS('
          SELECT m.id_manufacturer FROM '._DB_PREFIX_.'manufacturer m
          LEFT JOIN jtl_connector_link l ON CONCAT("m", m.id_manufacturer) = l.endpointId AND l.type = 16
          WHERE l.hostId IS NULL
        ');

        $return = array();

        foreach ($manufacturers as $manufacturer) {
            if (file_exists(_PS_MANU_IMG_DIR_.(int)$manufacturer['id_manufacturer'].'.jpg')) {
                $return[] = array(
                    'id' => 'm'.$manufacturer['id_manufacturer'],
                    'foreignKey'=> $manufacturer['id_manufacturer'],
                    'remoteUrl' => _PS_BASE_URL_._THEME_MANU_DIR_.$manufacturer['id_manufacturer'].'.jpg',
                    'filename' => $manufacturer['id_manufacturer'].'.jpg',
                    'relationType' => 'manufacturer'
                );
            }
        }

        return $return;
    }

    private function productImages()
    {
        $images = $this->db->executeS('
          SELECT i.* FROM '._DB_PREFIX_.'image i
          LEFT JOIN jtl_connector_link l ON i.id_image = l.endpointId AND l.type = 16
          WHERE l.hostId IS NULL
        ');

        $return = array();

        foreach ($images as $image) {
            $path = \Image::getImgFolderStatic($image['id_image']);

            if (file_exists(_PS_PROD_IMG_DIR_.$path.(int)$image['id_image'].'.jpg')) {
                $return[] = array(
                    'id' => $image['id_image'],
                    'foreignKey'=> $image['id_product'],
                    'remoteUrl' => _PS_BASE_URL_._THEME_PROD_DIR_.$path.$image['id_image'].'.jpg',
                    'filename' => $image['id_image'].'.jpg',
                    'relationType' => 'product',
                    'sort' => $image['position']
                );
            }
        }

        return $return;
    }
}
