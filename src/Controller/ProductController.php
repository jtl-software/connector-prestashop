<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Controller;

use AttributeGroup;
use Combination;
use Context;
use Jtl\Connector\Core\Controller\DeleteInterface;
use Jtl\Connector\Core\Controller\PullInterface;
use Jtl\Connector\Core\Controller\PushInterface;
use Jtl\Connector\Core\Definition\IdentityType;
use Jtl\Connector\Core\Exception\TranslatableAttributeException;
use Jtl\Connector\Core\Model\AbstractModel;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\Product as JtlProduct;
use Jtl\Connector\Core\Model\Product2Category as JtlProductCategory;
use Jtl\Connector\Core\Model\ProductI18n as JtlProductI18n;
use Jtl\Connector\Core\Model\ProductPrice as JtlPrice;
use Jtl\Connector\Core\Model\ProductPriceItem as JtlPriceItem;
use Jtl\Connector\Core\Model\ProductSpecialPrice as JtlSpecialPrice;
use Jtl\Connector\Core\Model\ProductSpecialPriceItem as JtlSpecialPriceItem;
use Jtl\Connector\Core\Model\ProductVariation as JtlProductVariation;
use Jtl\Connector\Core\Model\ProductVariationI18n as JtlProductVariationI18n;
use Jtl\Connector\Core\Model\ProductVariationValue as JtlProductVariationValue;
use Jtl\Connector\Core\Model\ProductVariationValueI18n as JtlProductVariationValueI18n;
use Jtl\Connector\Core\Model\QueryFilter;
use Jtl\Connector\Core\Model\Statistic;
use Jtl\Connector\Core\Model\TaxRate;
use Jtl\Connector\Core\Model\TranslatableAttribute as JtlTranslatableAttribute;
use Jtl\Connector\Core\Model\TranslatableAttributeI18n as JtlTranslatableAttributeI18n;
use jtl\Connector\Presta\Utils\QueryBuilder;
use Product as PrestaProduct;

class ProductController extends ProductPriceController implements PullInterface, PushInterface, DeleteInterface
{
    public const
        JTL_ATTRIBUTE_ACTIVE           = 'active',
        JTL_ATTRIBUTE_ONLINE_ONLY      = 'online_only',
        JTL_ATTRIBUTE_MAIN_CATEGORY_ID = 'main_category_id',
        JTL_ATTRIBUTE_CARRIERS         = 'carriers';

    protected array $jtlSpecialAttributes = [
        self::JTL_ATTRIBUTE_ACTIVE,
        self::JTL_ATTRIBUTE_ONLINE_ONLY,
        self::JTL_ATTRIBUTE_MAIN_CATEGORY_ID,
        self::JTL_ATTRIBUTE_CARRIERS
    ];

    /**
     * @param QueryFilter $queryFilter
     * @return array|AbstractModel[]
     * @throws TranslatableAttributeException
     * @throws \JsonException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function pull(QueryFilter $queryFilter): array
    {
        $jtlProducts = [];

        $prestaProductIds = $this->getNotLinkedEntities(
            $queryFilter,
            self::PRODUCT_LINKING_TABLE,
            'product',
            'id_product'
        );

        foreach ($prestaProductIds as $prestaProductId) {
            $product = $this->createJtlProduct(new PrestaProduct($prestaProductId['id_product']));
            if (isset($prestaProductId['id_product_attribute'])) {
                $prestaProduct = new PrestaProduct($product->getId()->getEndpoint());
                $jtlProducts[] = $this->createJtlProductsFromVariation(
                    $product,
                    (int)$prestaProductId['id_product_attribute'],
                    $prestaProduct
                );
            } else {
                $jtlProducts[] = $product;
            }
        }

        return $jtlProducts;
    }

    /**
     * @param QueryFilter $queryFilter
     * @param string $linkingTable
     * @param string $prestaTable
     * @param string $columns
     * @param string|null $fromDate
     * @return array
     * @throws \PrestaShopDatabaseException
     */
    protected function getNotLinkedEntities(
        QueryFilter $queryFilter,
        string      $linkingTable,
        string      $prestaTable,
        string      $columns,
        ?string     $fromDate = null
    ): array {
        $products = parent::getNotLinkedEntities($queryFilter, $linkingTable, $prestaTable, $columns, $fromDate);

        if (\count($products) < $queryFilter->getLimit()) {
            $sql = new QueryBuilder();
            $sql->setUsePrefix(false);

            $sql
                ->select('p.id_product as id_product, p.id_product_attribute as id_product_attribute')
                ->from(\_DB_PREFIX_ . 'product_attribute', 'p')
                ->leftJoin(\_DB_PREFIX_ . 'product', 'pr', 'pr.id_product = p.id_product')
                ->leftJoin(
                    self::PRODUCT_LINKING_TABLE,
                    'l',
                    'CONCAT(p.id_product, "_", p.id_product_attribute) = l.endpoint_id'
                )
                ->where('l.host_id IS NULL AND p.id_product > 0')
                ->limit($queryFilter->getLimit());


            $combis = $this->db->executeS($sql);

            $products = \array_merge($products, $combis);
        }

        return $products;
    }

    /**
     * @param PrestaProduct $prestaProduct
     * @return JtlProduct
     * @throws TranslatableAttributeException
     * @throws \JsonException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function createJtlProduct(PrestaProduct $prestaProduct): JtlProduct
    {
        $prestaAttributes = $prestaProduct->getAttributesGroups(Context::getContext()->language->id);
        $prestaStock      = new \StockAvailable($prestaProduct->id);

        $jtlProduct = (new JtlProduct())
            ->setId(new Identity((string)$prestaProduct->id))
            ->setManufacturerId(new Identity((string)$prestaProduct->id_manufacturer))
            ->setCreationDate($this->createDateTime($prestaProduct->date_add))
            ->setEan($prestaProduct->ean13)
            ->setIsbn($prestaProduct->isbn)
            ->setHeight((float)$prestaProduct->height)
            ->setWidth((float)$prestaProduct->width)
            ->setLength((float)$prestaProduct->depth)
            ->setIsMasterProduct(\count($prestaAttributes) > 0)
            ->setModified($this->createDateTime($prestaProduct->date_upd))
            ->setShippingWeight((float)$prestaProduct->weight)
            ->setSku($prestaProduct->reference)
            ->setUpc($prestaProduct->upc)
            ->setStockLevel($prestaStock->quantity)
            ->setSpecialPrices(...$this->createJtlSpecialPrices($prestaProduct))
            ->setVat($prestaProduct->getTaxesRate())
            ->setAttributes(...$this->createJtlSpecialAttributes($prestaProduct))
            ->setCategories(...$this->createJtlProductCategories($prestaProduct))
            ->setPrices($this->createJtlPrice((float)$prestaProduct->price))
            ->setI18ns(...$this->createJtlProductTranslations($prestaProduct->id))
            ->setAvailableFrom($this->createDateTime($prestaProduct->available_date))
            ->setBasePriceUnitName($prestaProduct->unity)
            ->setConsiderStock($prestaStock->out_of_stock === 0 || $prestaStock->out_of_stock === 2)
            ->setPermitNegativeStock($prestaStock->out_of_stock === 0)
            ->setIsActive(true)
            ->setIsTopProduct((bool)$prestaProduct->on_sale)
            ->setPurchasePrice((float)$prestaProduct->wholesale_price)
            ->setMinimumOrderQuantity((float)$prestaProduct->minimal_quantity)
            ->setManufacturerNumber($prestaProduct->mpn);

        if ($jtlProduct->getIsMasterProduct()) {
            $jtlProduct
                ->setMasterProductId(new Identity(""))
                ->setVariations(
                    ...$this->createJtlProductVariations(
                        $prestaAttributes
                    )
                );
        }

        return $jtlProduct;
    }

    /**
     * @param JtlProduct $product
     * @param int $variationId
     * @param PrestaProduct $presta
     * @return JtlProduct
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function createJtlProductsFromVariation(
        JtlProduct    $product,
        int           $variationId,
        PrestaProduct $presta
    ): JtlProduct {
        $comb       = new Combination($variationId);
        $attributes = $presta->getAttributesGroups(Context::getContext()->language->id, $variationId);

        return (new JtlProduct())
            ->setId(new Identity($product->getId()->getEndpoint() . '_' . (string)$variationId))
            ->setManufacturerId($product->getManufacturerId())
            ->setCreationDate($product->getCreationDate())
            ->setEan($comb->ean13)
            ->setIsbn($comb->isbn)
            ->setHeight($product->getHeight())
            ->setIsMasterProduct(false)
            ->setMasterProductId($product->getId())
            ->setModified($product->getModified())
            ->setShippingWeight($product->getShippingWeight())
            ->setSku($product->getSku() . '_' . (string)$variationId)
            ->setUpc($comb->upc)
            ->setStockLevel(\StockAvailable::getQuantityAvailableByProduct($presta->id, $variationId))
            ->setVat($product->getVat())
            ->setAttributes(...$product->getAttributes())
            ->setCategories(...$product->getCategories())
            ->setPrices($this->createJtlPrice($product->getPrices()[0]->getItems()[0]->getNetPrice(), $comb))
            ->setI18ns(...$product->getI18ns())
            ->setAvailableFrom($product->getAvailableFrom())
            ->setBasePriceUnitName($product->getBasePriceUnitName())
            ->setConsiderStock($product->getConsiderStock())
            ->setPermitNegativeStock($product->getPermitNegativeStock())
            ->setIsActive(true)
            ->setIsTopProduct($product->getIsTopProduct())
            ->setPurchasePrice($product->getPurchasePrice())
            ->setMinimumOrderQuantity((float)$comb->minimal_quantity)
            ->setManufacturerNumber($comb->mpn)
            ->setVariations(
                ...$this->createJtlProductVariations(
                    $attributes
                )
            );
    }


    /**
     * @param PrestaProduct $prestaproduct
     * @return array
     * @throws \PrestaShopDatabaseException
     */
    protected function getPrestaCarriers(PrestaProduct $prestaproduct): array
    {
        $prestaCarriers = [];

        $queryBuilder = new QueryBuilder();

        $sql = $queryBuilder
            ->select('id_carrier_reference')
            ->from('product_carrier')
            ->where('id_product = ' . $prestaproduct->id . ' AND id_shop = ' . $prestaproduct->id_shop_default);

        $results = $this->db->executeS($sql);

        foreach ($results as $result) {
            $prestaCarriers[] = $result['id_carrier_reference'];
        }

        return $prestaCarriers;
    }

    /**
     * @param int $productId
     * @return array
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function getPrestaSpecialPrices(int $productId): array
    {
        $queryBuilder        = new QueryBuilder();
        $prestaSpecialPrices = [];

        $sql = $queryBuilder
            ->select('id_specific_price')
            ->from('specific_price')
            ->where('id_product = ' . $productId);

        $results = $this->db->executeS($sql);

        foreach ($results as $result) {
            $prestaSpecialPrices[] = new \SpecificPrice($result['id_specific_price']);
        }

        return $prestaSpecialPrices;
    }

    /**
     * @param float $prestaPrice
     * @param Combination|null $combination
     * @return JtlPrice
     */
    protected function createJtlPrice(float $prestaPrice, Combination $combination = null): JtlPrice
    {
        $price = isset($combination) ? ($combination->price + $prestaPrice) : $prestaPrice;

        return (new JtlPrice())
            ->setCustomerGroupId(new Identity(""))
            ->setItems(
                (new JtlPriceItem())
                    ->setNetPrice((float)$price)
                    ->setQuantity(0)
            );
    }

    /**
     * @param PrestaProduct $prestaProduct
     * @return array
     * @throws \Exception
     */
    protected function createJtlSpecialPrices(PrestaProduct $prestaProduct): array
    {
        $prestaSpecialPrices = $this->getPrestaSpecialPrices($prestaProduct->id);
        $jtlSpecialPrices    = [];

        foreach ($prestaSpecialPrices as $prestaSpecialPrice) {
            /** @var \SpecificPrice $prestaSpecialPrice */
            $jtlSpecialPrices[] = (new JtlSpecialPrice())
                ->setIsActive(true)
                ->setActiveFromDate($this->createDateTime($prestaSpecialPrice->from))
                ->setActiveUntilDate($this->createDateTime($prestaSpecialPrice->to))
                ->setConsiderStockLimit(true)
                ->setStockLimit($prestaSpecialPrice->from_quantity)
                ->addItem($this->createJtlSpecialPriceItem($prestaSpecialPrice, $prestaProduct));
        }

        return $jtlSpecialPrices;
    }

    /**
     * @param \SpecificPrice $prestaSpecialPrice
     * @param PrestaProduct $prestaProduct
     * @return JtlSpecialPriceItem
     */
    protected function createJtlSpecialPriceItem(
        \SpecificPrice $prestaSpecialPrice,
        PrestaProduct  $prestaProduct
    ): JtlSpecialPriceItem {
        $priceType  = $prestaSpecialPrice->reduction_type;
        $netPrice   = $prestaProduct->price;
        $grossPrice = $netPrice / 100 * (100 + $prestaProduct->getTaxesRate());

        if ($priceType === 'percentage') {
            $priceReduction    = $grossPrice * $prestaSpecialPrice->reduction;
            $reducedGrossPrice = $grossPrice - $priceReduction;
            $reducedNetPrice   = $reducedGrossPrice / (100 + $prestaProduct->getTaxesRate()) * 100;
        } else {
            if ($prestaSpecialPrice->reduction_tax === 1) {
                $reducedGrossPrice = $grossPrice - $prestaSpecialPrice->reduction;
                $reducedNetPrice   = $reducedGrossPrice / (100 + $prestaProduct->getTaxesRate()) * 100;
            } else {
                $reducedNetPrice = $netPrice - $prestaSpecialPrice->reduction;
            }
        }

        return (new JtlSpecialPriceItem())
            ->setPriceNet($reducedNetPrice)
            ->setCustomerGroupId(new Identity(""));
    }

    /**
     * @param int $prestaProductId
     * @return array
     * @throws \PrestaShopDatabaseException
     */
    protected function createJtlProductTranslations(int $prestaProductId): array
    {
        $shopId = \Context::getContext()->shop->id;

        $sql = (new QueryBuilder())
            ->select('pl.*')
            ->from('product_lang', 'pl')
            ->leftJoin('lang', 'l', 'l.id_lang = pl.id_lang')
            ->where("pl.id_product = $prestaProductId AND pl.id_shop = $shopId");

        $results = $this->db->executeS($sql);

        $i18ns = [];

        foreach ($results as $result) {
            $i18ns[] = $this->createJtlProductTranslation($result);
        }

        return $i18ns;
    }

    /**
     * @param array $prestaProductI18n
     * @return JtlProductI18n
     * @throws \PrestaShopDatabaseException
     */
    protected function createJtlProductTranslation(array $prestaProductI18n): JtlProductI18n
    {
        return (new JtlProductI18n())
            ->setName((string)$prestaProductI18n['name'])
            ->setTitleTag((string)$prestaProductI18n['meta_title'])
            ->setDescription((string)$prestaProductI18n['description'])
            ->setShortDescription((string)$prestaProductI18n['description_short'])
            ->setMetaDescription((string)$prestaProductI18n['meta_description'])
            ->setLanguageIso($this->getJtlLanguageIsoFromLanguageId((int)$prestaProductI18n['id_lang']));
    }

    /**
     * @param PrestaProduct $prestaProduct
     * @return array
     * @throws TranslatableAttributeException
     * @throws \JsonException
     * @throws \PrestaShopDatabaseException
     */
    protected function createJtlSpecialAttributes(PrestaProduct $prestaProduct): array
    {
        $jtlAttributes  = [];
        $langIso        = $this->getJtlLanguageIsoFromLanguageId(\Context::getContext()->language->id);
        $prestaCarriers = $this->getPrestaCarriers($prestaProduct);

        if (!empty($prestaProduct->id_category_default)) {
            $jtlAttributes[] = (new JtlTranslatableAttribute())
                ->setId(new Identity(self::JTL_ATTRIBUTE_MAIN_CATEGORY_ID))
                ->addI18n(
                    (new JtlTranslatableAttributeI18n())
                        ->setName(self::JTL_ATTRIBUTE_MAIN_CATEGORY_ID)
                        ->setValue($prestaProduct->id_category_default)
                        ->setLanguageIso($langIso)
                );
        }

        if (!empty($prestaCarriers)) {
            $jtlAttributes[] = (new JtlTranslatableAttribute())
                ->setId(new Identity(self::JTL_ATTRIBUTE_CARRIERS))
                ->addI18n(
                    (new JtlTranslatableAttributeI18n())
                        ->setName(self::JTL_ATTRIBUTE_CARRIERS)
                        ->setValue(\implode(',', $prestaCarriers))
                        ->setLanguageIso($langIso)
                );
        }

        if ($prestaProduct->online_only) {
            $jtlAttributes[] = (new JtlTranslatableAttribute())
                ->setId(new Identity(self::JTL_ATTRIBUTE_ONLINE_ONLY))
                ->addI18n(
                    (new JtlTranslatableAttributeI18n())
                        ->setName(self::JTL_ATTRIBUTE_ONLINE_ONLY)
                        ->setValue(1)
                        ->setLanguageIso($langIso)
                );
        }

        return $jtlAttributes;
    }

    /**
     * @param PrestaProduct $prestaProduct
     * @return array
     */
    protected function createJtlProductCategories(PrestaProduct $prestaProduct): array
    {
        $jtlProductCategories = [];

        foreach ($prestaProduct->getCategories() as $category) {
            $jtlProductCategory = (new JtlProductCategory())
                ->setCategoryId(new Identity((string)$category))
                ->setId(new Identity($prestaProduct->id . '_' . $category));

            $jtlProductCategories[] = $jtlProductCategory;
        }

        return $jtlProductCategories;
    }

    /**
     * @param array $variations
     * @return array
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function createJtlProductVariations(array $variations): array
    {
        $jtlProductVariations = [];
        $prestaVariations     = [];
        $prestaAttributes     = [];

        foreach ($variations as $variation) {
            $prestaVariations[$variation['id_attribute_group']] = $variation['group_name'];
            $prestaAttributes[$variation['id_attribute']]       = $variation;
        }

        foreach ($prestaVariations as $id => $prestaVariation) {
            $jtlProductVariations[] = $this->createJtlProductVariation($id);
        }

        foreach ($jtlProductVariations as $jtlProductVariation) {
            $jtlProductVariation->setValues(
                ...$this->createJtlProductVariationValues(
                    (int)$jtlProductVariation->getId()->getEndpoint(),
                    $prestaAttributes
                )
            );
        }

        return $jtlProductVariations;
    }

    /**
     * @param int $prestaVariationId
     * @return JtlProductVariation
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function createJtlProductVariation(
        int $prestaVariationId
    ): JtlProductVariation {
        return (new JtlProductVariation())
            ->setId(new Identity((string)$prestaVariationId))
            ->setI18ns(...$this->createJtlProductVariationI18ns($prestaVariationId));
    }

    /**
     * @param int $prestaVariationId
     * @return array
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function createJtlProductVariationI18ns(int $prestaVariationId): array
    {
        $jtlProductVariationI18ns = [];
        $attributeGroup           = new AttributeGroup($prestaVariationId);

        foreach ($attributeGroup->name as $key => $prestaVariationName) {
            $jtlProductVariationI18ns[] = (new JtlProductVariationI18n())
                ->setName($prestaVariationName)
                ->setLanguageIso($this->getJtlLanguageIsoFromLanguageId($key));
        }

        return $jtlProductVariationI18ns;
    }

    /**
     * @param int $variationId
     * @param array $prestaVariations
     * @return array
     * @throws \PrestaShopDatabaseException
     */
    protected function createJtlProductVariationValues(int $variationId, array $prestaVariations): array
    {
        $languages                      = \Language::getLanguages();
        $prestaVariationValuesByLangIds = [];
        $jtlVariationValues             = [];

        foreach ($languages as $language) {
            $langId = $language['id_lang'];
            foreach ($prestaVariations as $prestaVariation) {
                if ((int)$prestaVariation['id_attribute_group'] === $variationId) {
                    $comb                                                                      = new \ProductAttribute(
                        $prestaVariation['id_attribute'],
                        $langId
                    );
                    $prestaVariationValuesByLangIds[$prestaVariation['id_attribute']][$langId] = $comb->name;
                }
            }
        }

        foreach ($prestaVariationValuesByLangIds as $key => $prestaVariationValuesByLangId) {
            $jtlVariationValues[] = $this->createJtlProductVariationValue($key, $prestaVariationValuesByLangId);
        }

        return $jtlVariationValues;
    }

    /**
     * @param int $prestaAttributeId
     * @param array $prestaVariationValue
     * @return JtlProductVariationValue
     * @throws \PrestaShopDatabaseException
     */
    protected function createJtlProductVariationValue(
        int   $prestaAttributeId,
        array $prestaVariationValue
    ): JtlProductVariationValue {
        $attribute = new \ProductAttribute($prestaAttributeId);

        return (new JtlProductVariationValue())
            ->setId(new Identity((string)$prestaAttributeId))
            ->setSort((int)$attribute->position)
            ->setI18ns(...$this->createJtlProductVariationValueI18ns($prestaVariationValue));
    }

    /**
     * @param array $prestaVariationI18ns
     * @return array
     * @throws \PrestaShopDatabaseException
     */
    protected function createJtlProductVariationValueI18ns(array $prestaVariationI18ns): array
    {
        $attributeI18ns = [];

        foreach ($prestaVariationI18ns as $langId => $prestaVariationI18n) {
            $attributeI18ns[] = (new JtlProductVariationValueI18n())
                ->setName($prestaVariationI18n)
                ->setLanguageIso($this->getJtlLanguageIsoFromLanguageId($langId));
        }

        return $attributeI18ns;
    }

    /**
     * @param AbstractModel $jtlProduct
     * @return AbstractModel
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function push(AbstractModel $jtlProduct): AbstractModel
    {
        /** @var JtlProduct $jtlProduct */
        $endpoint        = $jtlProduct->getId()->getEndpoint();
        $masterProductId = $jtlProduct->getMasterProductId()->getEndpoint();

        $stockLevelController = new ProductStockLevelController($this->mapper);
        $stockLevelController->setLogger($this->logger);

        $isNew = empty($endpoint);

        // create or update var combination
        if (!empty($masterProductId)) {
            $combiProductId = $this->createPrestaProductVariation($jtlProduct, new PrestaProduct($masterProductId));
            // if a product was recreated we might have dead links in the linking table
            $this->mapper->delete(IdentityType::PRODUCT, null, $jtlProduct->getId()->getHost());
            $this->mapper->save(IdentityType::PRODUCT, $combiProductId, $jtlProduct->getId()->getHost());

            // price
            parent::push($jtlProduct);
            // stock
            $stockLevelController->push($jtlProduct);

            return $jtlProduct;
        }

        // existing product
        if (!$isNew) {
            $prestaProduct = $this->createPrestaProduct($jtlProduct, new PrestaProduct($endpoint));
            $this->updatePrestaProductCategories($jtlProduct, $prestaProduct);

            if (!$prestaProduct->update()) {
                throw new \RuntimeException('Error updating product ' . $jtlProduct->getI18ns()[0]->getName());
            }

            // price
            parent::push($jtlProduct);
            // stock
            $stockLevelController->push($jtlProduct);

            return $jtlProduct;
        }
        // new product
        $prestaProduct = $this->createPrestaProduct($jtlProduct, new PrestaProduct());

        try {
            if (!$prestaProduct->add()) {
                throw new \RuntimeException(
                    \sprintf(
                        'Error creating product %s',
                        $jtlProduct->getI18ns()[0]->getName()
                    )
                );
            }
        } catch (\PrestaShopException $e) {
            throw new \RuntimeException(
                \sprintf(
                    'Error saving product %s | Message from PrestaShop: %s',
                    $jtlProduct->getI18ns()[0]->getName(),
                    $e->getMessage()
                )
            );
        }

        $this->updatePrestaProductCategories($jtlProduct, $prestaProduct);

        $jtlProduct->getId()->setEndpoint((string)$prestaProduct->id);

        // if a product was recreated we might have dead links in the linking table
        $this->mapper->delete(IdentityType::PRODUCT, null, $jtlProduct->getId()->getHost());
        $this->mapper->save(
            IdentityType::PRODUCT,
            $jtlProduct->getId()->getEndpoint(),
            $jtlProduct->getId()->getHost()
        );

        // price
        parent::push($jtlProduct);
        // stock
        $stockLevelController->push($jtlProduct);

        return new $jtlProduct;
    }

    /**
     * @param JtlProduct $jtlProduct
     * @param PrestaProduct $prestaProduct
     * @return PrestaProduct
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function createPrestaProduct(JtlProduct $jtlProduct, PrestaProduct $prestaProduct): PrestaProduct
    {
        $translations  = $this->createPrestaProductTranslations(...$jtlProduct->getI18ns());
        $categories    = $jtlProduct->getCategories();
        $firstCategory = $categories > 0 ? \array_shift($categories) : null;

        $prestaProduct->id                  = $jtlProduct->getId()->getEndpoint();
        $prestaProduct->id_manufacturer     = $jtlProduct->getManufacturerId()->getEndpoint();
        $prestaProduct->id_category_default =
            \is_null($firstCategory)
                ? null
                : $firstCategory->getCategoryId()->getEndpoint();
        $prestaProduct->date_add            = $jtlProduct->getCreationDate()?->format('Y-m-d H:i:s');
        $prestaProduct->date_upd            = $jtlProduct->getModified()?->format('Y-m-d H:i:s');
        $prestaProduct->ean13               = $jtlProduct->getEan();
        $prestaProduct->height              = $jtlProduct->getHeight();
        $prestaProduct->depth               = $jtlProduct->getLength();
        $prestaProduct->width               = $jtlProduct->getWidth();
        $prestaProduct->weight              = $jtlProduct->getShippingWeight();
        $prestaProduct->reference           = $jtlProduct->getSku();
        $prestaProduct->upc                 = $jtlProduct->getUpc();
        $prestaProduct->isbn                = $jtlProduct->getIsbn();
        $prestaProduct->id_tax_rules_group  = $this->findTaxClassId(...$jtlProduct->getTaxRates());
        $prestaProduct->unity               = $this->createPrestaBasePrice($jtlProduct);
        $prestaProduct->available_date      = $jtlProduct->getAvailableFrom()?->format('Y-m-d H:i:s');
        $prestaProduct->active              = $jtlProduct->getIsActive();
        $prestaProduct->on_sale             = $jtlProduct->getIsTopProduct();
        $prestaProduct->minimal_quantity    = $jtlProduct->getMinimumOrderQuantity();
        $prestaProduct->mpn                 = $jtlProduct->getManufacturerNumber();
        $prestaProduct->price               =
            \round($jtlProduct->getPrices()[0]->getItems()[0]->getNetPrice(), 6);
        $prestaProduct->wholesale_price     =
            $prestaProduct->price / 100 * (100 + (new \Tax($prestaProduct->id_tax_rules_group))->rate);

        foreach ($translations as $key => $translation) {
            $prestaProduct->name[$key]              = $translation['name'];
            $prestaProduct->description[$key]       = $translation['description'];
            $prestaProduct->description_short[$key] = $translation['description_short'];
            $prestaProduct->link_rewrite[$key]      = $translation['link_rewrite'];
            $prestaProduct->meta_description[$key]  = \str_split($translation['meta_description'], 512) [0];
            $prestaProduct->meta_keywords[$key]     = $translation['meta_keywords'];
            $prestaProduct->meta_title[$key]        = $translation['meta_title'];
        }

        $this->pushSpecialAttributes($jtlProduct, $prestaProduct);

        return $prestaProduct;
    }

    /**
     * @param JtlProduct $jtlProduct
     * @param PrestaProduct $prestaProduct
     * @return bool
     */
    protected function updatePrestaProductCategories(JtlProduct $jtlProduct, PrestaProduct $prestaProduct): bool
    {
        $categoryIds = [];

        foreach ($jtlProduct->getCategories() as $category) {
            $categoryIds[] = $category->getCategoryId()->getEndpoint();
        }

        return $prestaProduct->updateCategories($categoryIds);
    }

    /**
     * @param JtlProduct $jtlProduct
     * @param PrestaProduct $prestaProduct
     * @return string
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function createPrestaProductVariation(JtlProduct $jtlProduct, PrestaProduct $prestaProduct): string
    {
        $valueIds = [];
        foreach ($jtlProduct->getVariations() as $jtlVariation) {
            $groupId = $this->createPrestaAttributeGroup($jtlVariation);
            foreach ($jtlVariation->getValues() as $jtlVariationValue) {
                $valueIds[] = $this->createPrestaAttribute($jtlVariationValue, $groupId);
            }
        }

        $combiId = $this->createPrestaCombination(
            $valueIds,
            (int)$jtlProduct->getMasterProductId()->getEndpoint()
        );


        $taxRate        = (new \Tax($this->findTaxClassId(...$jtlProduct->getTaxRates())))->rate;
        $price          = \round($jtlProduct->getPrices()[0]->getItems()[0]->getNetPrice(), 6);
        $wholeSalePrice = \round(
            $jtlProduct->getPrices()[0]->getItems()[0]->getNetPrice() / 100 * (100 + $taxRate),
            6
        );
        $prestaProduct->updateAttribute(
            $combiId,
            max($wholeSalePrice - $prestaProduct->wholesale_price, 0.0),
            max($price - $prestaProduct->price, 0.0),
            $jtlProduct->getShippingWeight(),
            null,
            null,
            null,
            $jtlProduct->getSku(),
            $jtlProduct->getEan(),
            null,
            null,
            $jtlProduct->getUpc(),
            $jtlProduct->getMinimumOrderQuantity() < 1 ? 1 : \ceil($jtlProduct->getMinimumOrderQuantity()),
            null,
            true,
            [],
            $jtlProduct->getIsbn(),
            null,
            null,
            $jtlProduct->getManufacturerNumber()
        );

        $endpointId = $jtlProduct->getMasterProductId()->getEndpoint() . '_' . $combiId;

        $jtlProduct->getId()->setEndpoint($endpointId);

        return $endpointId;
    }

    /**
     * @param JtlProductVariation $jtlVariation
     * @return int
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function createPrestaAttributeGroup(JtlProductVariation $jtlVariation): int
    {
        $allowedTypes = [
            JtlProductVariation::TYPE_SELECT,
            JtlProductVariation::TYPE_RADIO
        ];

        $name = $jtlVariation->getI18ns()[0]->getName();
        $sql  = (new QueryBuilder())
            ->select('id_attribute_group')
            ->from('attribute_group_lang')
            ->where("name = '$name'");

        $groupId   = $this->db->getValue($sql);
        $groupType = \in_array(
            $jtlVariation->getType(),
            $allowedTypes
        ) ? $jtlVariation->getType() : JtlProductVariation::TYPE_SELECT;

        $groupTranslations = $this->createPrestaAttributeGroupTranslations($jtlVariation);

        $group             = new AttributeGroup($groupId > 0 ? $groupId : null);
        $group->group_type = $groupType;

        foreach ($groupTranslations as $key => $translation) {
            $group->name[$key]        = $translation['name'];
            $group->public_name[$key] = $translation['public_name'];
        }

        $group->save();

        return (int)$group->id;
    }

    /**
     * @param JtlProductVariation $jtlVariation
     * @return array
     * @throws \PrestaShopDatabaseException
     */
    protected function createPrestaAttributeGroupTranslations(JtlProductVariation $jtlVariation): array
    {
        $translations = [];

        foreach ($jtlVariation->getI18ns() as $i18n) {
            $langId                               = $this->getPrestaLanguageIdFromIso($i18n->getLanguageIso());
            $translations[$langId]['name']        = $i18n->getName();
            $translations[$langId]['public_name'] = $i18n->getName();
        }

        return $translations;
    }

    /**
     * @param JtlProductVariationValue $jtlValue
     * @param int $prestaAttributeGroupId
     * @return int
     * @throws \PrestaShopException
     */
    protected function createPrestaAttribute(JtlProductVariationValue $jtlValue, int $prestaAttributeGroupId): int
    {
        $name = $jtlValue->getI18ns()[0]->getName();

        $sql = (new QueryBuilder())
            ->select('l.id_attribute')
            ->from('attribute_lang', 'l')
            ->leftJoin('attribute', 'a', 'a.id_attribute = l.id_attribute')
            ->where("l.name = '$name' && a.id_attribute_group = $prestaAttributeGroupId");

        $attributeId = $this->db->getValue($sql);

        $attributeTranslations = $this->createPrestaAttributeTranslations($jtlValue);

        $attribute                     = new \ProductAttribute($attributeId > 0 ? $attributeId : null);
        $attribute->id_attribute_group = $prestaAttributeGroupId;
        $attribute->position           = $jtlValue->getSort();

        foreach ($attributeTranslations as $key => $attributeTranslation) {
            $attribute->name[$key] = $attributeTranslation['name'];
        }

        $attribute->save();


        return (int)$attribute->id;
    }

    /**
     * @param JtlProductVariationValue $jtlValue
     * @return array
     * @throws \PrestaShopDatabaseException
     */
    protected function createPrestaAttributeTranslations(JtlProductVariationValue $jtlValue): array
    {
        $translations = [];

        foreach ($jtlValue->getI18ns() as $i18n) {
            $langId                        = $this->getPrestaLanguageIdFromIso($i18n->getLanguageIso());
            $translations[$langId]['name'] = $i18n->getName();
        }

        return $translations;
    }

    /**
     * @param array $prestaAttributeIds
     * @param int $prestaProductId
     * @return string
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function createPrestaCombination(
        array $prestaAttributeIds,
        int   $prestaProductId
    ): string {
        $ids      = \implode(',', $prestaAttributeIds);
        $countIds = \count($prestaAttributeIds);
        $sql      = (new QueryBuilder())
            ->select('id_product_attribute')
            ->from('product_attribute_combination')
            ->where("id_attribute IN ($ids)")
            ->groupBy('id_product_attribute')
            ->having("COUNT(DISTINCT id_attribute) = $countIds");

        $combiId = $this->db->getValue($sql);

        $combi             = new Combination($combiId > 0 ? $combiId : null);
        $combi->price      = 0;
        $combi->id_product = $prestaProductId;
        $combi->save();
        $combi->setAttributes($prestaAttributeIds);

        return (string)$combi->id;
    }

    /**
     * @param JtlProductI18n ...$jtlProductI18ns
     * @return array
     * @throws \PrestaShopDatabaseException
     */
    protected function createPrestaProductTranslations(JtlProductI18n ...$jtlProductI18ns): array
    {
        $translations = [];

        foreach ($jtlProductI18ns as $jtlProductI18n) {
            $langId = $this->getPrestaLanguageIdFromIso($jtlProductI18n->getLanguageIso());

            $translations[$langId]['name']              = $jtlProductI18n->getName();
            $translations[$langId]['description']       = \strip_tags($jtlProductI18n->getDescription());
            $translations[$langId]['description_short'] = \strip_tags($jtlProductI18n->getShortDescription());
            $translations[$langId]['link_rewrite']      = $jtlProductI18n->getUrlPath();
            $translations[$langId]['meta_description']  = $jtlProductI18n->getMetaDescription();
            $translations[$langId]['meta_keywords']     = $jtlProductI18n->getMetaKeywords();
            $translations[$langId]['meta_title']        = $jtlProductI18n->getTitleTag();
        }

        return $translations;
    }

    /**
     * @param TaxRate ...$taxRates
     * @return int|null
     * @throws \PrestaShopDatabaseException
     */
    protected function findTaxClassId(TaxRate ...$taxRates): ?int
    {
        $activeCountries = \Country::getCountries(Context::getContext()->language->id, true);
        $jtlTaxes        = [];
        $prestaTaxes     = [];
        $conditions      = [];

        foreach ($taxRates as $taxRate) {
            if (\array_key_exists($this->getPrestaCountryIdFromIso($taxRate->getCountryIso()), $activeCountries)) {
                $jtlTaxes[] = $taxRate;
            }
        }

        foreach (\Tax::getTaxes() as $tax) {
            $prestaTaxes[$tax['rate']] = $tax['id_tax'];
        }

        foreach ($jtlTaxes as $jtlTax) {
            if (!empty($jtlTax->getRate())) {
                $conditions[] = \sprintf(
                    'id_country = %s AND id_tax = %s',
                    $this->getPrestaCountryIdFromIso($jtlTax->getCountryIso()),
                    $prestaTaxes[\number_format($jtlTax->getRate(), 3)]
                );
            }
        }

        $sql = (new QueryBuilder())
            ->select('id_tax_rules_group, COUNT(id_tax_rules_group) AS hits')
            ->from('tax_rule')
            ->where(\join(' OR ', $conditions))
            ->groupBy('id_tax_rules_group')
            ->orderBy('hits DESC');

        return $this->db->executeS($sql)[0]['id_tax_rules_group'] ?? null;
    }

    /**
     * @param JtlProduct $jtlProduct
     * @return string
     */
    protected function createPrestaBasePrice(JtlProduct $jtlProduct): string
    {
        $unit = '';
        if ($jtlProduct->getConsiderBasePrice()) {
            $basePriceQuantity =
                $jtlProduct->getBasePriceQuantity() !== 1. ? (string)$jtlProduct->getBasePriceQuantity() : '';
            $unit              = \sprintf('%s%s', $basePriceQuantity, $jtlProduct->getBasePriceUnitCode());
        }
        return $unit;
    }

    /**
     * @param JtlProduct $jtlProduct
     * @param PrestaProduct $prestaProduct
     * @return void
     * @throws TranslatableAttributeException
     */
    private function pushSpecialAttributes(JtlProduct $jtlProduct, PrestaProduct $prestaProduct): void
    {
        $specialAttributes = [
            'online_only' => 'online_only',
            'products_status' => 'active',
            'main_category_id' => 'id_category_default',
            'carriers' => 'carriers'
        ];


        $foundSpecialAttributes = [];
        foreach ($jtlProduct->getAttributes() as $attribute) {
            foreach ($attribute->getI18ns() as $i18n) {
                $name = \array_search($i18n->getName(), $specialAttributes);
                if ($name === false) {
                    $name = $i18n->getName();
                }

                if (isset($specialAttributes[$name])) {
                    $foundSpecialAttributes[$name] = $i18n->getValue();
                    break;
                }
            }
        }

        foreach ($foundSpecialAttributes as $key => $value) {
            switch ($key) {
                case 'main_category_id':
                    foreach ($jtlProduct->getCategories() as $product2Category) {
                        if (
                            $product2Category->getCategoryId()->getHost() === (int)$value
                            && $product2Category->getCategoryId()->getEndpoint() !== ''
                        ) {
                            $value = (int)$product2Category->getCategoryId()->getEndpoint();
                        }
                    }
                    break;
                case 'carriers':
                    $carrierIds = \array_map('intval', \explode(',', $value));
                    if (!\in_array($carrierIds, ['', '0'])) {
                        $prestaProduct->setCarriers($carrierIds);
                    }
                    break;
            }


            $prestaProduct->{$specialAttributes[$key]} = $value;
        }

        $prices               = $jtlProduct->getPrices();
        $prestaProduct->price = \round(\end($prices)->getItems()[0]->getNetPrice(), 6);

        $rrp = $jtlProduct->getRecommendedRetailPrice();
        if ($rrp > $prestaProduct->price) {
            $this->saveRecommendedRetailPriceAsFeature($prestaProduct, $rrp);
        }
    }


    /**
     * @param PrestaProduct $prestaProduct
     * @param float $rrp
     * @return PrestaProduct
     */
    protected function saveRecommendedRetailPriceAsFeature(PrestaProduct $prestaProduct, float $rrp): PrestaProduct
    {
        $translations = [];
        foreach (\Language::getLanguages() as $language) {
            $translations[$language['id_lang']] = [
                'name' => 'recommended_retail_price',
                'value' => $rrp
            ];
        }

        return $prestaProduct;
    }


    /**
     * @param AbstractModel $model
     * @return AbstractModel
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function delete(AbstractModel $model): AbstractModel
    {
        $endpoint = $model->getId()->getEndpoint();
        if ($endpoint !== '') {
            $isCombi = \str_contains($model->getId()->getEndpoint(), '_');
            if (!$isCombi) {
                $obj = new \Product($endpoint);
            } else {
                $combiId = \explode('_', $model->getId()->getEndpoint())[1];
                $obj     = new Combination($combiId);
            }

            $obj->delete();
        }

        return $model;
    }

    /**
     * @return Statistic
     */
    public function statistic(): Statistic
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->setUsePrefix(false);

        $countSql = $queryBuilder
            ->select('COUNT(*)')
            ->from(\_DB_PREFIX_ . 'product', 'p')
            ->leftJoin(self::PRODUCT_LINKING_TABLE, 'l', 'CAST(p.id_product AS CHAR) = l.endpoint_id')
            ->where('l.host_id IS NULL AND p.id_product > 0');

        $count = $this->db->getValue($countSql);

        $queryBuilderVars = new QueryBuilder();
        $queryBuilderVars->setUsePrefix(false);

        $countVarsSql = $queryBuilderVars
            ->select('COUNT(*)')
            ->from(\_DB_PREFIX_ . 'product_attribute', 'pa')
            ->leftJoin(
                self::PRODUCT_LINKING_TABLE,
                'la',
                'CONCAT(pa.id_product, "_", pa.id_product_attribute) = la.endpoint_id'
            )
            ->where('la.host_id IS NULL AND pa.id_product > 0');

        $countVars = $this->db->getValue($countVarsSql);

        return (new Statistic())
            ->setAvailable((int)$count + $countVars)
            ->setControllerName($this->controllerName);
    }
}
