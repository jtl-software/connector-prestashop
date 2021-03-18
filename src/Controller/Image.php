<?php

namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Formatter\ExceptionFormatter;
use jtl\Connector\Linker\IdentityLinker;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use jtl\Connector\Presta\Utils\Utils;
use Context;

class Image extends BaseController
{
    /**
     * @var PrimaryKeyMapper|null
     */
    protected $primaryKeyMapper = null;

    /**
     * Image constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->primaryKeyMapper = new PrimaryKeyMapper();
    }

    public function pullData($data, $model, $limit = null)
    {
        $imgData = array_merge(
            $this->productImages(),
            $this->categoryImages(),
            $this->manufacturerImages()
        );

        $return = [];

        foreach ($imgData as $img) {
            $model = $this->mapper->toHost($img);

            $return[] = $model;
        }

        return $return;
    }

    public function pushData($data)
    {
        $id = $data->getForeignKey()->getEndpoint();

        if (!empty($id)) {

            if (in_array($data->getRelationType(), ['category', 'manufacturer'])) {
                $this->deleteData($data);
            }

            $generate_hight_dpi_images = (bool)\Configuration::get('PS_HIGHT_DPI');

            switch ($data->getRelationType()) {
                case 'category':
                    \ImageManager::resize($data->getFilename(), _PS_CAT_IMG_DIR_.$id.'.jpg', null, null, 'jpg');

                    if (file_exists(_PS_CAT_IMG_DIR_.$id.'.jpg')) {
                        $images_types = \ImageType::getImagesTypes('categories');
                        foreach ($images_types as $k => $image_type) {
                            \ImageManager::resize(
                                _PS_CAT_IMG_DIR_.$id.'.jpg',
                                _PS_CAT_IMG_DIR_.$id.'-'.stripslashes($image_type['name']).'.jpg',
                                (int)$image_type['width'],
                                (int)$image_type['height']
                            );

                            if ($generate_hight_dpi_images) {
                                \ImageManager::resize(
                                    _PS_CAT_IMG_DIR_.$id.'.jpg',
                                    _PS_CAT_IMG_DIR_.$id.'-'.stripslashes($image_type['name']).'2x.jpg',
                                    (int)$image_type['width']*2,
                                    (int)$image_type['height']*2
                                );
                            }
                        }
                    }

                    $data->getId()->setEndpoint('c'.$id);

                    break;

                case 'manufacturer':
                    \ImageManager::resize($data->getFilename(), _PS_MANU_IMG_DIR_.$id.'.jpg', null, null, 'jpg');

                    if (file_exists(_PS_MANU_IMG_DIR_.$id.'.jpg')) {
                        $images_types = \ImageType::getImagesTypes('manufacturers');
                        foreach ($images_types as $k => $image_type) {
                            \ImageManager::resize(
                                _PS_MANU_IMG_DIR_.$id.'.jpg',
                                _PS_MANU_IMG_DIR_.$id.'-'.stripslashes($image_type['name']).'.jpg',
                                (int)$image_type['width'],
                                (int)$image_type['height']
                            );

                            if ($generate_hight_dpi_images) {
                                \ImageManager::resize(
                                    _PS_MANU_IMG_DIR_.$id.'.jpg',
                                    _PS_MANU_IMG_DIR_.$id.'-'.stripslashes($image_type['name']).'2x.jpg',
                                    (int)$image_type['width']*2,
                                    (int)$image_type['height']*2
                                );
                            }
                        }
                    }

                    $data->getId()->setEndpoint('m'.$id);

                    break;

                case 'product':
                    list($productId, $combiId) = Utils::explodeProductEndpoint($id);

                    $identity = $data->getId();
                    $isUpdate = $identity->getEndpoint() === "" ? false : true;

                    $img = new \Image($isUpdate ? (int)$identity->getEndpoint() : null);
                    $img->id_product = $productId;
                    $img->position = $data->getSort();

                    $defaultImageLegend = false;
                    $defaultLanguageId = (int) Context::getContext()->language->id;

                    foreach ($data->getI18ns() as $imageI18n) {
                        $languageId = Utils::getInstance()->getLanguageIdByIso($imageI18n->getLanguageISO());
                        if ($languageId !== false && $defaultLanguageId === (int)$languageId) {
                            $defaultImageLegend = $imageI18n->getAltText();
                            break;
                        }
                    }

                    if ($defaultImageLegend !== false) {
                        $img->legend = $defaultImageLegend;
                    }

                    if (empty($combiId) && $img->position == 1) {
                        $img->cover =  1;

                        $coverId = \Product::getCover($productId);
                        if (isset($coverId['id_image'])) {
                            $oldCover = new \Image($coverId['id_image']);
                            $oldCover->cover = 0;
                            $oldCover->save();
                        }
                    }

                    $img->save();

                    $new_path = $img->getPathForCreation();
                    \ImageManager::resize($data->getFilename(), $new_path.'.jpg');

                    if (file_exists($new_path.'.jpg')) {
                        $imagesTypes = \ImageType::getImagesTypes('products');
                        foreach ($imagesTypes as $k => $image_type) {
                            \ImageManager::resize($data->getFilename(), $new_path . '-' . stripslashes($image_type['name']) . '.jpg', $image_type['width'], $image_type['height'], null);
                        }
                    }

                    if (!is_null($combiId) && $isUpdate === false) {
                        $this->db->execute('INSERT INTO '._DB_PREFIX_.'product_attribute_image SET id_product_attribute='.$combiId.', id_image='.$img->id);
                    }

                    $data->getId()->setEndpoint($img->id);

                    break;
            }
        }

        return $data;
    }

    public function deleteData($data)
    {
        $fId = $data->getForeignKey()->getEndpoint();

        if (!empty($fId)) {
            switch ($data->getRelationType()) {
                case 'category':
                    $cat = new \Category($fId);
                    $cat->deleteImage();
                    break;

                case 'manufacturer':
                    $manufacturer = new \Manufacturer($fId);
                    $manufacturer->deleteImage();
                    break;

                case 'product':
                    $id = $data->getId()->getEndpoint();
                    if (!empty($id)) {
                        $img = new \Image((int)$id);
                        $img->delete();
                    }
                    break;
            }
        }

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
          LEFT JOIN jtl_connector_link_image l ON CONCAT("c", c.id_category) = l.endpoint_id
          WHERE l.host_id IS NULL
        ');

        $return = [];

        foreach ($categories as $category) {
            if (file_exists(_PS_CAT_IMG_DIR_.(int)$category['id_category'].'.jpg')) {
                $return[] = [
                    'id' => 'c'.$category['id_category'],
                    'foreignKey'=> $category['id_category'],
                    'remoteUrl' => _PS_BASE_URL_._THEME_CAT_DIR_.$category['id_category'].'.jpg',
                    'filename' => $category['id_category'].'.jpg',
                    'relationType' => 'category'
                ];
            }
        }

        return $return;
    }

    private function manufacturerImages()
    {
        $manufacturers = $this->db->executeS('
          SELECT m.id_manufacturer FROM '._DB_PREFIX_.'manufacturer m
          LEFT JOIN jtl_connector_link_image l ON CONCAT("m", m.id_manufacturer) = l.endpoint_id
          WHERE l.host_id IS NULL
        ');

        $return = [];

        foreach ($manufacturers as $manufacturer) {
            if (file_exists(_PS_MANU_IMG_DIR_.(int)$manufacturer['id_manufacturer'].'.jpg')) {
                $return[] = [
                    'id' => 'm'.$manufacturer['id_manufacturer'],
                    'foreignKey'=> $manufacturer['id_manufacturer'],
                    'remoteUrl' => _PS_BASE_URL_._THEME_MANU_DIR_.$manufacturer['id_manufacturer'].'.jpg',
                    'filename' => $manufacturer['id_manufacturer'].'.jpg',
                    'relationType' => 'manufacturer'
                ];
            }
        }

        return $return;
    }

    private function productImages()
    {
        $images = $this->db->executeS('
          SELECT i.* FROM '._DB_PREFIX_.'image i
          LEFT JOIN jtl_connector_link_image l ON i.id_image = l.endpoint_id
          WHERE l.host_id IS NULL
        ');

        $return = [];

        foreach ($images as $image) {
            $path = \Image::getImgFolderStatic($image['id_image']);

            if (file_exists(_PS_PROD_IMG_DIR_.$path.(int)$image['id_image'].'.jpg')) {
                $return[] = [
                    'id' => $image['id_image'],
                    'foreignKey'=> $image['id_product'],
                    'remoteUrl' => _PS_BASE_URL_._THEME_PROD_DIR_.$path.$image['id_image'].'.jpg',
                    'filename' => $image['id_image'].'.jpg',
                    'relationType' => 'product',
                    'sort' => $image['position']
                ];
            }
        }

        return $return;
    }
}
