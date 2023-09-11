<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Controller;

use Jtl\Connector\Core\Controller\DeleteInterface;
use Jtl\Connector\Core\Controller\PullInterface;
use Jtl\Connector\Core\Controller\PushInterface;
use Jtl\Connector\Core\Model\AbstractImage;
use Jtl\Connector\Core\Model\AbstractModel;
use Jtl\Connector\Core\Model\CategoryImage;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\ManufacturerImage;
use Jtl\Connector\Core\Model\ProductImage;
use Jtl\Connector\Core\Model\QueryFilter;
use Jtl\Connector\Core\Model\ImageI18n;
use Jtl\Connector\Core\Model\Statistic;
use jtl\Connector\Presta\Utils\QueryBuilder;

class ImageController extends AbstractController implements PushInterface, PullInterface, DeleteInterface
{
    /**
     * @param QueryFilter $queryFilter
     * @return array|AbstractModel[]
     */
    public function pull(QueryFilter $queryFilter): array
    {
        $productImages      = $this->getProductImages($queryFilter);
        $categoryImages     = $this->getCategoryImages($queryFilter);
        $manufacturerImages = $this->getManufacturerImages($queryFilter);

        return \array_merge($productImages, $categoryImages, $manufacturerImages);
    }

    /**
     * @param QueryFilter $queryFilter
     * @return array
     * @throws \PrestaShopDatabaseException
     */
    private function getProductImages(QueryFilter $queryFilter): array
    {
        $images = $this->getNotLinkedEntities(
            $queryFilter,
            self::IMAGE_LINKING_TABLE,
            'image',
            'id_image'
        );

        $prestaImages = [];
        $jtlImages    = [];

        foreach ($images as $image) {
            $path = \Image::getImgFolderStatic($image['id_image']);

            if (\file_exists(\_PS_PROD_IMG_DIR_ . $path . $image['id_image'] . '.jpg')) {
                $prestaImages[] = [
                    'id'           => (string)$image['id_image'],
                    'foreignKey'   => (string)$image['id_product'],
                    'remoteUrl'    => \_PS_BASE_URL_ . \_THEME_PROD_DIR_ . $path . $image['id_image'] . '.jpg',
                    'filename'     => $image['id_image'] . '.jpg',
                    'relationType' => 'product',
                    'sort'         => $image['position']
                ];
            }
        }

        foreach ($prestaImages as $prestaImage) {
            $jtlImages[] = $this->createJtlImage($prestaImage);
        }

        return $jtlImages;
    }

    /**
     * @param QueryFilter $queryFilter
     * @return array
     * @throws \PrestaShopDatabaseException
     */
    private function getCategoryImages(QueryFilter $queryFilter): array
    {
        $images = $this->getNotLinkedEntities(
            $queryFilter,
            self::IMAGE_LINKING_TABLE,
            'category',
            'id_category'
        );

        $prestaImages = [];
        $jtlImages    = [];

        foreach ($images as $image) {
            if (\file_exists(\_PS_CAT_IMG_DIR_ . $image['id_category'] . '.jpg')) {
                $prestaImages[] = [
                    'id'           => 'c' . $image['id_category'],
                    'foreignKey'   => (string)$image['id_category'],
                    'remoteUrl'    => \_PS_BASE_URL_ . \_THEME_CAT_DIR_ . $image['id_category'] . '.jpg',
                    'filename'     => $image['id_category'] . '.jpg',
                    'relationType' => 'category'
                ];
            }
        }

        foreach ($prestaImages as $prestaImage) {
            $jtlImages[] = $this->createJtlImage($prestaImage);
        }

        return $jtlImages;
    }

    /**
     * @param QueryFilter $queryFilter
     * @return array
     * @throws \PrestaShopDatabaseException
     */
    private function getManufacturerImages(QueryFilter $queryFilter): array
    {
        $images = $this->getNotLinkedEntities(
            $queryFilter,
            self::IMAGE_LINKING_TABLE,
            'manufacturer',
            'id_manufacturer'
        );

        $prestaImages = [];
        $jtlImages    = [];

        foreach ($images as $image) {
            if (\file_exists(\_PS_MANU_IMG_DIR_ . (int)$image['id_manufacturer'] . '.jpg')) {
                $prestaImages[] = [
                    'id'           => 'm' . $image['id_manufacturer'],
                    'foreignKey'   => (string)$image['id_manufacturer'],
                    'remoteUrl'    => \_PS_BASE_URL_ . \_THEME_MANU_DIR_ . $image['id_manufacturer'] . '.jpg',
                    'filename'     => $image['id_manufacturer'] . '.jpg',
                    'relationType' => 'manufacturer'
                ];
            }
        }

        foreach ($prestaImages as $prestaImage) {
            $jtlImages[] = $this->createJtlImage($prestaImage);
        }

        return $jtlImages;
    }

    /**
     * @param array $image
     * @return AbstractImage
     */
    protected function createJtlImage(array $image): AbstractImage
    {
        switch ($image['relationType']) {
            case 'product':
                $jtlImage = new ProductImage();
                break;
            case 'category':
                $jtlImage = new CategoryImage();
                break;
            case 'manufacturer':
                $jtlImage = new ManufacturerImage();
                break;
        }

        $jtlImage
            ->setId(new Identity($image['id']))
            ->setForeignKey(new Identity($image['foreignKey']))
            ->setFilename($image['filename'])
            ->setRemoteUrl($image['remoteUrl'])
            ->setI18ns(...$this->createJtlImageI18ns($jtlImage));

        return $jtlImage;
    }

    /**
     * @param AbstractImage $image
     * @return array
     * @throws \PrestaShopDatabaseException
     */
    protected function createJtlImageI18ns(AbstractImage $image): array
    {
        $prestaI18ns = $this->getPrestaImageI18n($image);
        $jtlI18ns    = [];

        foreach ($prestaI18ns as $prestaI18n) {
            $jtlI18ns[] = (new ImageI18n())
                ->setAltText($prestaI18n['altText'])
                ->setLanguageIso($this->getJtlLanguageIsoFromLanguageId($prestaI18n['id_lang']));
        }

        return $jtlI18ns;
    }

    /**
     * @param AbstractImage $image
     * @return array
     * @throws \PrestaShopDatabaseException
     */
    protected function getPrestaImageI18n(AbstractImage $image): array
    {
        $queryBuilder = new QueryBuilder();
        $id           = \is_numeric(\substr($image->getId()->getEndpoint(), 0, 1))
            ? $image->getId()->getEndpoint()
            : \substr($image->getId()->getEndpoint(), 1);

        $sql = $queryBuilder
            ->select('il.id_lang, il.legend as altText')
            ->from('image_lang', 'il')
            ->leftJoin('lang', 'l', 'l.id_lang = il.id_lang')
            ->where("l.id_lang IS NOT NULL AND il.id_image = $id");

        return $this->db->executeS($sql);
    }

    /**
     * @param AbstractModel $jtlImage
     * @return AbstractModel
     * @throws \Jtl\Connector\Core\Exception\DefinitionException
     */
    public function push(AbstractModel $jtlImage): AbstractModel
    {
        /** @var AbstractImage $jtlImage */
        $id = $jtlImage->getForeignKey()->getEndpoint();

        if (!empty($id)) {
            if (\in_array($jtlImage->getRelationType(), ['category', 'manufacturer'])) {
                $this->delete($jtlImage);
            }

            $generate_hight_dpi_images = (bool)\Configuration::get('PS_HIGHT_DPI');

            switch ($jtlImage->getRelationType()) {
                case 'category':
                    $this->createPrestaCategoryImage($jtlImage, $generate_hight_dpi_images, $id);
                    break;
                case 'manufacturer':
                    $this->createPrestaManufacturerImage($jtlImage, $generate_hight_dpi_images, $id);
                    break;
                case 'product':
                    $this->createPrestaProductImage($jtlImage, $id);
                    break;
            }
        }

        return $jtlImage;
    }

    /**
     * @param AbstractImage $jtlImage
     * @param bool $hightDpi
     * @param string $id
     * @return AbstractImage
     * @throws \PrestaShopDatabaseException
     */
    protected function createPrestaCategoryImage(AbstractImage $jtlImage, bool $hightDpi, string $id): AbstractImage
    {
        \ImageManager::resize($jtlImage->getFilename(), \_PS_CAT_IMG_DIR_ . $id . '.jpg', null, null, 'jpg');

        if (\file_exists(\_PS_CAT_IMG_DIR_ . $id . '.jpg')) {
            $images_types = \ImageType::getImagesTypes('categories');
            foreach ($images_types as $k => $image_type) {
                \ImageManager::resize(
                    \_PS_CAT_IMG_DIR_ . $id . '.jpg',
                    \_PS_CAT_IMG_DIR_ . $id . '-' . \stripslashes($image_type['name']) . '.jpg',
                    (int)$image_type['width'],
                    (int)$image_type['height']
                );

                if ($hightDpi) {
                    \ImageManager::resize(
                        \_PS_CAT_IMG_DIR_ . $id . '.jpg',
                        \_PS_CAT_IMG_DIR_ . $id . '-' . \stripslashes($image_type['name']) . '2x.jpg',
                        (int)$image_type['width'] * 2,
                        (int)$image_type['height'] * 2
                    );
                }
            }
        }

        $jtlImage->getId()->setEndpoint('c' . $id);

        return $jtlImage;
    }

    /**
     * @param AbstractImage $jtlImage
     * @param bool $hightDpi
     * @param string $id
     * @return AbstractImage
     * @throws \PrestaShopDatabaseException
     */
    protected function createPrestaManufacturerImage(AbstractImage $jtlImage, bool $hightDpi, string $id): AbstractImage
    {
        \ImageManager::resize(
            $jtlImage->getFilename(),
            \_PS_MANU_IMG_DIR_ . $id . '.jpg',
            null,
            null,
            'jpg'
        );

        if (\file_exists(\_PS_MANU_IMG_DIR_ . $id . '.jpg')) {
            $images_types = \ImageType::getImagesTypes('manufacturers');
            foreach ($images_types as $k => $image_type) {
                \ImageManager::resize(
                    \_PS_MANU_IMG_DIR_ . $id . '.jpg',
                    \_PS_MANU_IMG_DIR_ . $id . '-' . \stripslashes($image_type['name']) . '.jpg',
                    (int)$image_type['width'],
                    (int)$image_type['height']
                );

                if ($hightDpi) {
                    \ImageManager::resize(
                        \_PS_MANU_IMG_DIR_ . $id . '.jpg',
                        \_PS_MANU_IMG_DIR_ . $id . '-' . \stripslashes($image_type['name']) . '2x.jpg',
                        (int)$image_type['width'] * 2,
                        (int)$image_type['height'] * 2
                    );
                }
            }
        }

        $jtlImage->getId()->setEndpoint('m' . $id);

        return $jtlImage;
    }

    /**
     * @param AbstractImage $jtlImage
     * @param string $id
     * @return AbstractImage
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function createPrestaProductImage(AbstractImage $jtlImage, string $id): AbstractImage
    {
        list($productId, $combiId) = \array_pad(\explode('_', $id, 2), 2, null);

        $identity = $jtlImage->getId();
        $isUpdate = $identity->getEndpoint() !== "";

        $img             = new \Image($isUpdate ? (int)$identity->getEndpoint() : null);
        $img->id_product = $productId;
        $img->position   = $jtlImage->getSort();

        $defaultImageLegend = false;
        $defaultLanguageId  = \Context::getContext()->language->id;

        foreach ($jtlImage->getI18ns() as $imageI18n) {
            $languageId = $this->getPrestaLanguageIdFromIso($imageI18n->getLanguageISO());
            if ($defaultLanguageId === $languageId) {
                $defaultImageLegend = $imageI18n->getAltText();
                break;
            }
        }

        if ($defaultImageLegend !== false) {
            $img->legend = $defaultImageLegend;
        }

        if (empty($combiId) && $img->position == 1) {
            $img->cover = 1;

            $coverId = \Product::getCover($productId);
            if (isset($coverId['id_image'])) {
                $oldCover        = new \Image($coverId['id_image']);
                $oldCover->cover = 0;
                $oldCover->save();
            }
        }

        $img->save();

        $new_path = $img->getPathForCreation();
        \ImageManager::resize($jtlImage->getFilename(), $new_path . '.jpg');

        if (\file_exists($new_path . '.jpg')) {
            $imagesTypes = \ImageType::getImagesTypes('products');
            foreach ($imagesTypes as $k => $image_type) {
                \ImageManager::resize(
                    $jtlImage->getFilename(),
                    $new_path . '-' . \stripslashes($image_type['name']) . '.jpg',
                    $image_type['width'],
                    $image_type['height'],
                    null
                );
            }
        }

        //TODO: rewrite to not directly insert into database.
        if (!\is_null($combiId) && $isUpdate === false) {
            $this->db->execute(
                'INSERT INTO ' . \_DB_PREFIX_ . 'product_attribute_image 
                            SET id_product_attribute=' . $combiId . ', id_image=' . $img->id
            );
        }

        try {
            \Hook::exec('actionWatermark', ['id_image' => $img->id, 'id_product' => $img->id_product]);
        } catch (\PrestaShopException $e) {
            $this->logger->error(
                \sprintf(
                    "Watermark Hook returned Exception for id_img: %s id_product: %s",
                    $img->id,
                    $img->id_product
                )
            );
        }

        $jtlImage->getId()->setEndpoint($img->id);

        return $jtlImage;
    }

    /**
     * @param AbstractModel $jtlImage
     * @return AbstractModel
     * @throws \Jtl\Connector\Core\Exception\DefinitionException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function delete(AbstractModel $jtlImage): AbstractModel
    {
        /** @var AbstractImage $jtlImage */
        $fId = $jtlImage->getForeignKey()->getEndpoint();

        if (!empty($fId)) {
            switch ($jtlImage->getRelationType()) {
                case 'category':
                    $cat = new \Category($fId);
                    $cat->deleteImage();
                    break;

                case 'manufacturer':
                    $manufacturer = new \Manufacturer($fId);
                    $manufacturer->deleteImage();
                    break;

                case 'product':
                    $id = $jtlImage->getId()->getEndpoint();
                    if (!empty($id)) {
                        $img = new \Image((int)$id);
                        $img->delete();
                    }
                    break;
            }
        }

        return $jtlImage;
    }

    /**
     * @return Statistic
     * @throws \PrestaShopDatabaseException
     */
    public function statistic(): Statistic
    {
        $queryFilter = new QueryFilter();
        $imgData     = \array_merge(
            $this->getProductImages($queryFilter),
            $this->getCategoryImages($queryFilter),
            $this->getManufacturerImages($queryFilter)
        );

        return (new Statistic())
            ->setAvailable(\count($imgData))
            ->setControllerName($this->controllerName);
    }
}