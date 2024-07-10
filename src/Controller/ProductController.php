<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Controller;

use AttributeGroup;
use Combination;
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
use Jtl\Connector\Core\Model\ProductVariation;
use Jtl\Connector\Core\Model\ProductVariation as JtlProductVariation;
use Jtl\Connector\Core\Model\ProductVariationI18n as JtlProductVariationI18n;
use Jtl\Connector\Core\Model\ProductVariationValue as JtlProductVariationValue;
use Jtl\Connector\Core\Model\ProductVariationValueI18n as JtlProductVariationValueI18n;
use Jtl\Connector\Core\Model\QueryFilter;
use Jtl\Connector\Core\Model\Statistic;
use Jtl\Connector\Core\Model\TaxRate;
use Jtl\Connector\Core\Model\TranslatableAttribute;
use Jtl\Connector\Core\Model\ProductAttribute as JtlProductAttribute;
use Jtl\Connector\Core\Model\TranslatableAttributeI18n as JtlTranslatableAttributeI18n;
use jtl\Connector\Presta\Utils\QueryBuilder;
use jtl\Connector\Presta\Utils\Utils;
use Product as PrestaProduct;

class ProductController extends ProductPriceController implements PullInterface, PushInterface, DeleteInterface
{
    public const
        JTL_ATTRIBUTE_ACTIVE           = 'active',
        JTL_ATTRIBUTE_ONLINE_ONLY      = 'online_only',
        JTL_ATTRIBUTE_MAIN_CATEGORY_ID = 'main_category_id',
        JTL_ATTRIBUTE_CARRIERS         = 'carriers',
        JTL_ATTRIBUTE_MAIN_VARIANT     = 'main_variant';

    /** @var array<string> */
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
            $product = $this->createJtlProduct(new PrestaProduct((int)$prestaProductId['id_product']));
            if (isset($prestaProductId['id_product_attribute'])) {
                $prestaProduct = new PrestaProduct((int)$product->getId()->getEndpoint());
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
     * @param string      $linkingTable
     * @param string      $prestaTable
     * @param string      $columns
     * @param string|null $fromDate
     * @return array<int, array<string, string>>
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

            if (!\is_array($combis)) {
                throw new \RuntimeException('Error fetching product combinations');
            }

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
        $prestaAttributes = $prestaProduct->getAttributesGroups($this->getPrestaContextLanguageId());
        $prestaStock      = new \StockAvailable($prestaProduct->id);
        $prestaProductId  = \is_int($prestaProduct->id)
            ? $prestaProduct->id
            : throw new \RuntimeException('Product ID is not an integer');

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
            ->setI18ns(...$this->createJtlProductTranslations($prestaProductId))
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
     * @param JtlProduct    $product
     * @param int           $variationId
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
        $attributes = $presta->getAttributesGroups($this->getPrestaContextLanguageId(), $variationId);

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
     * @return array<int>
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

        if (\is_array($results)) {
            foreach ($results as $result) {
                $prestaCarriers[] = $result['id_carrier_reference'];
            }
        }

        return $prestaCarriers;
    }

    /**
     * @param int $productId
     * @return array<\SpecificPrice>
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

        if (\is_array($results)) {
            foreach ($results as $result) {
                $prestaSpecialPrices[] = new \SpecificPrice($result['id_specific_price']);
            }
        }

        return $prestaSpecialPrices;
    }

    /**
     * @param float            $prestaPrice
     * @param Combination|null $combination
     * @return JtlPrice
     */
    protected function createJtlPrice(float $prestaPrice, ?Combination $combination = null): JtlPrice
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
     * @return array<JtlSpecialPrice>
     * @throws \Exception
     */
    protected function createJtlSpecialPrices(PrestaProduct $prestaProduct): array
    {
        $prestaProductId = $prestaProduct->id;

        if (!\is_int($prestaProductId)) {
            throw new \RuntimeException('Product ID is not an integer');
        }

        $prestaSpecialPrices = $this->getPrestaSpecialPrices($prestaProductId);
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
     * @param PrestaProduct  $prestaProduct
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
     * @return array<JtlProductI18n>
     * @throws \PrestaShopDatabaseException
     */
    protected function createJtlProductTranslations(int $prestaProductId): array
    {
        $shopId = $this->getPrestaContextShopId();

        $sql = (new QueryBuilder())
            ->select('pl.*')
            ->from('product_lang', 'pl')
            ->leftJoin('lang', 'l', 'l.id_lang = pl.id_lang')
            ->where("pl.id_product = $prestaProductId AND pl.id_shop = $shopId");

        $results = $this->db->executeS($sql);

        $i18ns = [];

        if (\is_array($results)) {
            foreach ($results as $result) {
                $i18ns[] = $this->createJtlProductTranslation($result);
            }
        }

        return $i18ns;
    }

    /**
     * @param array{
     *     id_product: int,
     *     id_shop: int,
     *     id_lang: int,
     *     description: string,
     *     description_short: string,
     *     link_rewrite: string,
     *     meta_description: string,
     *     meta_keywords: string,
     *     meta_title: string,
     *     name: string,
     *     available_now: string,
     *     available_later: string,
     *     delivery_in_stock: string,
     *     delivery_out_stock: string,
     * } $prestaProductI18n
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
     * @return array<JtlProductAttribute>
     * @throws TranslatableAttributeException
     * @throws \JsonException
     * @throws \PrestaShopDatabaseException
     */
    protected function createJtlSpecialAttributes(PrestaProduct $prestaProduct): array
    {
        $jtlAttributes  = [];
        $langIso        = $this->getJtlLanguageIsoFromLanguageId($this->getPrestaContextLanguageId());
        $prestaCarriers = $this->getPrestaCarriers($prestaProduct);

        if (!empty($prestaProduct->id_category_default)) {
            $jtlAttributes[] = (new JtlProductAttribute())
                ->setId(new Identity(self::JTL_ATTRIBUTE_MAIN_CATEGORY_ID))
                ->addI18n(
                    (new JtlTranslatableAttributeI18n())
                        ->setName(self::JTL_ATTRIBUTE_MAIN_CATEGORY_ID)
                        ->setValue($prestaProduct->id_category_default)
                        ->setLanguageIso($langIso)
                );
        }

        if (!empty($prestaCarriers)) {
            $jtlAttributes[] = (new JtlProductAttribute())
                ->setId(new Identity(self::JTL_ATTRIBUTE_CARRIERS))
                ->addI18n(
                    (new JtlTranslatableAttributeI18n())
                        ->setName(self::JTL_ATTRIBUTE_CARRIERS)
                        ->setValue(\implode(',', $prestaCarriers))
                        ->setLanguageIso($langIso)
                );
        }

        if ($prestaProduct->online_only) {
            $jtlAttributes[] = (new JtlProductAttribute())
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
     * @return array<JtlProductCategory>
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
     * @param array<int, array{
     *      id_attribute_group: int,
     *      is_color_group: int,
     *      group_name: string,
     *      public_group_name: string,
     *      id_attribute: int,
     *      attribute_name: string,
     *      attribute_color: string,
     *      id_product_attribute: int,
     *      quantity: int,
     *      price: string,
     *      ecotax: string,
     *      weight: string,
     *      default_on: int,
     *      reference: string,
     *      ean13: string,
     *      mpn: string,
     *      upc: string,
     *      isbn: string,
     *      unit_price_impact: string,
     *      minimal_quantity: int,
     *      available_date: string,
     *      group_type: string,
     *      available_now: string,
     *      available_later: string
     *     }
     * > $variations
     * @return ProductVariation[]
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
     * @return array<JtlProductVariationI18n>
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function createJtlProductVariationI18ns(int $prestaVariationId): array
    {
        $jtlProductVariationI18ns = [];
        $attributeGroup           = new AttributeGroup($prestaVariationId);

        if (\is_array($attributeGroup->name)) {
            foreach ($attributeGroup->name as $key => $prestaVariationName) {
                $jtlProductVariationI18ns[] = (new JtlProductVariationI18n())
                    ->setName($prestaVariationName)
                    ->setLanguageIso($this->getJtlLanguageIsoFromLanguageId($key));
            }
        }

        return $jtlProductVariationI18ns;
    }

    /**
     * @param int               $variationId
     * @param array<int, array{
     *       id_attribute_group: int,
     *       is_color_group: int,
     *       group_name: string,
     *       public_group_name: string,
     *       id_attribute: int,
     *       attribute_name: string,
     *       attribute_color: string,
     *       id_product_attribute: int,
     *       quantity: int,
     *       price: string,
     *       ecotax: string,
     *       weight: string,
     *       default_on: int,
     *       reference: string,
     *       ean13: string,
     *       mpn: string,
     *       upc: string,
     *       isbn: string,
     *       unit_price_impact: string,
     *       minimal_quantity: int,
     *       available_date: string,
     *       group_type: string,
     *       available_now: string,
     *       available_later: string
     *      }
     *  > $prestaVariations
     * @return array<JtlProductVariationValue>
     * @throws \PrestaShopDatabaseException
     */
    protected function createJtlProductVariationValues(int $variationId, array $prestaVariations): array
    {
        $languages                      = \Language::getLanguages();
        $prestaVariationValuesByLangIds = [];
        $jtlVariationValues             = [];

        foreach ($languages as $language) {
            if (!\is_array($language)) {
                throw new \RuntimeException('Language is not an array');
            }
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
            /** @var array<int, string> $prestaVariationValuesByLangId */
            $jtlVariationValues[] = $this->createJtlProductVariationValue($key, $prestaVariationValuesByLangId);
        }

        return $jtlVariationValues;
    }

    /**
     * @param int                $prestaAttributeId
     * @param array<int, string> $prestaVariationValue
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
     * @param array<int, string> $prestaVariationI18ns
     * @return array<JtlProductVariationValueI18n>
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

        // 1. check if product exists
        // 2. if not, create minimal product if has variation, create minimal variants
        // 3. update product if has variation, update variants
        // 4. update categories
        // 5. update price
        // 6. update stock


        $isNew = empty($endpoint);

        if (!$isNew) {
            if (empty($masterProductId)) {
                list($checkEndpoint, $_) = Utils::explodeProductEndpoint($endpoint, 0);
                // sanity check, does the product *really* exist?
                $prestaProduct = new PrestaProduct((int)$checkEndpoint);
                if ($prestaProduct->id === null) {
                    // product does not exist, we need to recreate it
                    $this->mapper->delete(IdentityType::PRODUCT, $endpoint);
                    $isNew = true;
                }
            } else {
                list($checkEndpoint, $combiId) = Utils::explodeProductEndpoint($endpoint, 0);
                // sanity check, does the variant *really* exist?
                $combination = new Combination((int)$combiId);
                if ($combination->id != $combiId) { // loose comparison on purpose
                    // variant does not exist, we need to recreate it
                    $this->mapper->delete(IdentityType::PRODUCT, $endpoint);
                    $isNew = true;
                }
            }
        }

        if (!empty($masterProductId)) {
            // sanity check, does the master product exist?
            $masterProduct = new PrestaProduct((int)$masterProductId);
            if ($masterProduct->id === null) {
                // FATAL we can not create a new master from a variant
                throw new \RuntimeException(
                    \sprintf(
                        'Master product (Host: %s, Endpoint: %s) does not exist',
                        $jtlProduct->getMasterProductId()->getHost(),
                        $jtlProduct->getMasterProductId()->getEndpoint()
                    )
                );
            }
        }

        try {
            // create minimal product
            if ($isNew) {
                if (empty($masterProductId)) {
                    // create empty normal product
                    $prestaProduct = new PrestaProduct();
                    $prestaProduct->save();

                    $jtlProduct->getId()->setEndpoint((string)$prestaProduct->id);
                } else {
                    $this->createMinPrestaVariant($jtlProduct, new PrestaProduct((int)$masterProductId));
                }

                $this->mapper->delete(IdentityType::PRODUCT, null, $jtlProduct->getId()->getHost());
                $this->mapper->save(
                    IdentityType::PRODUCT,
                    $jtlProduct->getId()->getEndpoint(),
                    $jtlProduct->getId()->getHost()
                );
            }

            // update product
            if (empty($masterProductId)) {
                // update normal product
                $prestaProduct = $this->updatePrestaProduct($jtlProduct, new PrestaProduct((int)$endpoint));
                if (!$prestaProduct->update()) {
                    throw new \RuntimeException('Error updating product ' . $jtlProduct->getI18ns()[0]->getName());
                }
            } else {
                // update var combination
                [$endpoint, $combiId] = Utils::explodeProductEndpoint($jtlProduct->getId()->getEndpoint(), 0);

                $prestaProduct = new PrestaProduct((int)$endpoint);
                $prestaProduct = $this->updatePrestaVariant($jtlProduct, $prestaProduct, (int)$combiId);

                if (!$prestaProduct->update()) {
                    throw new \RuntimeException('Error updating product ' . $jtlProduct->getI18ns()[0]->getName());
                }
            }
            // update categories
            $this->updatePrestaProductCategories($jtlProduct, $prestaProduct);
            // update price
            parent::push($jtlProduct);
            // update stock
            $stockLevelController->push($jtlProduct);

            return $jtlProduct;
            // done
        } catch (\Exception $e) {
            if ($isNew) {
                if (empty($masterProductId)) {
                    // delete partial product
                    try {
                        $prestaProduct = new PrestaProduct((int)$jtlProduct->getId()->getEndpoint());
                        $prestaProduct->delete();
                        $this->mapper->delete(IdentityType::PRODUCT, null, $jtlProduct->getId()->getHost());
                    } catch (\PrestaShopException $e) {
                        // ignore
                    }
                } else {
                    [$endpoint, $combiId] = Utils::explodeProductEndpoint($jtlProduct->getId()->getEndpoint(), 0);
                    if ($combiId !== null) {
                        // delete partial var combination
                        try {
                            $prestaProduct = new PrestaProduct((int)$endpoint);
                            $prestaProduct->deleteAttributeCombination((int)$combiId);
                            $this->mapper->delete(IdentityType::PRODUCT, null, $jtlProduct->getId()->getHost());
                        } catch (\PrestaShopException $e) {
                            // ignore
                        }
                    }
                }
            }
            throw new \RuntimeException(
                \sprintf(
                    'Error saving product %s | Message from PrestaShop: %s',
                    $jtlProduct->getI18ns()[0]->getName(),
                    $e->getMessage()
                )
            );
        }
    }

    protected function createMinPrestaVariant(JtlProduct $jtlProduct, PrestaProduct $prestaProduct): PrestaProduct
    {
        // create attribute for combination
        $combiId = $prestaProduct->addAttribute(
            .0,
            $jtlProduct->getShippingWeight(),
            .0,
            .0,
            [],
            $jtlProduct->getSku(),
            $jtlProduct->getEan(),
            false,
            null,
            $jtlProduct->getUpc()
        );
        if (!$combiId) {
            throw new \RuntimeException('Error creating product combination');
        }

        $this->createPrestaCombination($jtlProduct, $prestaProduct, $combiId);

        $jtlProduct->getId()->setEndpoint(Utils::joinProductEndpoint((string)$prestaProduct->id, (string)$combiId));

        return $prestaProduct;
    }

    protected function updatePrestaVariant(
        JtlProduct $jtlProduct,
        PrestaProduct $prestaProduct,
        int $combiId
    ): PrestaProduct {
        $minOrder = \ceil($jtlProduct->getMinimumOrderQuantity());
        $minOrder = \max($minOrder, 1);

        $isDefault = false;

        foreach ($jtlProduct->getAttributes() as $attribute) {
            foreach ($attribute->getI18ns() as $i18n) {
                if (
                    $i18n->getName() === self::JTL_ATTRIBUTE_MAIN_VARIANT
                    && $i18n->getValue(TranslatableAttribute::TYPE_BOOL)
                ) {
                    // $isDefault = true;
                    // TODO fix duplicated key error
                    break 2;
                }
            }
        }

        $this->createPrestaCombination($jtlProduct, $prestaProduct, $combiId);

        $prestaProduct->updateAttribute(
            $combiId,
            \max(\round($jtlProduct->getPurchasePrice(), 4), .0),
            .0,  // price gets set in price controller
            $jtlProduct->getShippingWeight(),
            .0,
            .0,
            // @phpstan-ignore-next-line
            null, // keeps assigned image, only works if not array
            $jtlProduct->getSku(),
            $jtlProduct->getEan(),
            $isDefault,
            null,
            $jtlProduct->getUpc(),
            (int)$minOrder
        );

        $prestaProduct->checkDefaultAttributes();

        return $prestaProduct;
    }

    /**
     * @param JtlProduct    $jtlProduct
     * @param PrestaProduct $prestaProduct
     * @return PrestaProduct
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function updatePrestaProduct(JtlProduct $jtlProduct, PrestaProduct $prestaProduct): PrestaProduct
    {
        $translations = $this->createPrestaProductTranslations(...$jtlProduct->getI18ns());
        $categories   = $jtlProduct->getCategories();

        $prestaProduct->id                  = (int)$jtlProduct->getId()->getEndpoint();
        $prestaProduct->id_manufacturer     = (int)$jtlProduct->getManufacturerId()->getEndpoint();
        $prestaProduct->id_category_default = (int)$categories[0]->getCategoryId()->getEndpoint();
        $prestaProduct->date_add            = $jtlProduct->getCreationDate()?->format('Y-m-d H:i:s') ?? '';
        $prestaProduct->date_upd            = $jtlProduct->getModified()?->format('Y-m-d H:i:s') ?? '';
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
        $prestaProduct->available_date      = $jtlProduct->getAvailableFrom()?->format('Y-m-d H:i:s') ?? '';
        $prestaProduct->active              = $jtlProduct->getIsActive();
        $prestaProduct->on_sale             = $jtlProduct->getIsTopProduct();
        $prestaProduct->minimal_quantity    = (int)$jtlProduct->getMinimumOrderQuantity();
        $prestaProduct->mpn                 = $jtlProduct->getManufacturerNumber();
        $prestaProduct->wholesale_price     = \max(\round($jtlProduct->getPurchasePrice(), 4), .0);

        foreach ($translations as $key => $translation) {
            $prestaProduct->name[$key]              = $translation['name'];
            $prestaProduct->description[$key]       = $translation['description'];
            $prestaProduct->description_short[$key] = $translation['description_short'];
            $prestaProduct->link_rewrite[$key]      = $translation['link_rewrite'];
            $prestaProduct->meta_description[$key]  = $translation['meta_description'];
            $prestaProduct->meta_keywords[$key]     = $translation['meta_keywords'];
            $prestaProduct->meta_title[$key]        = $translation['meta_title'];
        }

        $this->pushSpecialAttributes($jtlProduct, $prestaProduct);

        return $prestaProduct;
    }

    /**
     * @param JtlProduct    $jtlProduct
     * @param PrestaProduct $prestaProduct
     * @return bool
     */
    protected function updatePrestaProductCategories(JtlProduct $jtlProduct, PrestaProduct $prestaProduct): bool
    {
        $categoryIds = [];

        foreach ($jtlProduct->getCategories() as $category) {
            $categoryIds[] = (int)$category->getCategoryId()->getEndpoint();
        }

        $return = $prestaProduct->updateCategories($categoryIds);

        if (!$return && !$prestaProduct->update()) {
            throw new \RuntimeException('Error updating product ' . $jtlProduct->getI18ns()[0]->getName());
        }

        return $return;
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

        $groupType = \in_array(
            $jtlVariation->getType(),
            $allowedTypes
        ) ? $jtlVariation->getType() : JtlProductVariation::TYPE_SELECT;

        $groupTranslations = $this->createPrestaAttributeGroupTranslations($jtlVariation, \ucfirst($groupType));

        $name = \array_values($groupTranslations)[0]['name']; // we don't need a specific language here, any will do
        $sql  = (new QueryBuilder())
            ->select('id_attribute_group')
            ->from('attribute_group_lang')
            ->where("name = '$name'");

        $groupId = $this->db->getValue($sql);

        $group = new AttributeGroup($groupId > 0 ? (int)$groupId : null);
        // loose check because presta does the same
        if ($group->group_type != 'color') {
            $group->group_type = $groupType;
        }


        foreach ($groupTranslations as $key => $translation) {
            $group->name[$key]        = $translation['name'];
            $group->public_name[$key] = $translation['public_name'];
        }

        $group->save();

        return (int)$group->id;
    }

    /**
     * @param JtlProductVariation $jtlVariation
     * @return array<int, array<string, string>>
     * @throws \PrestaShopDatabaseException
     */
    protected function createPrestaAttributeGroupTranslations(
        JtlProductVariation $jtlVariation,
        string $groupType
    ): array {
        $translations = [];

        foreach ($jtlVariation->getI18ns() as $i18n) {
            $langId                               = $this->getPrestaLanguageIdFromIso($i18n->getLanguageIso());
            $translations[$langId]['name']        = \sprintf('%s (%s)', $i18n->getName(), \ucfirst($groupType));
            $translations[$langId]['public_name'] = $i18n->getName();
        }

        return $translations;
    }

    /**
     * @param JtlProductVariationValue $jtlValue
     * @param int                      $prestaAttributeGroupId
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

        $attribute                     = new \ProductAttribute($attributeId > 0 ? (int)$attributeId : null);
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
     * @return array<int, array<string, string>>
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
     * @param JtlProductI18n ...$jtlProductI18ns
     * @return array<int, array<string, string>>
     * @throws \PrestaShopDatabaseException
     */
    protected function createPrestaProductTranslations(JtlProductI18n ...$jtlProductI18ns): array
    {
        $translations = [];

        foreach ($jtlProductI18ns as $jtlProductI18n) {
            $langId = $this->getPrestaLanguageIdFromIso($jtlProductI18n->getLanguageIso());

            $translations[$langId]['name']              = $jtlProductI18n->getName();
            $translations[$langId]['description']       = $jtlProductI18n->getDescription();
            $translations[$langId]['description_short'] = $jtlProductI18n->getShortDescription();
            $translations[$langId]['meta_description']  = $jtlProductI18n->getMetaDescription();
            $translations[$langId]['meta_keywords']     = $jtlProductI18n->getMetaKeywords();
            $translations[$langId]['meta_title']        = $jtlProductI18n->getTitleTag();
            $translations[$langId]['link_rewrite']      = \Tools::str2url(
                empty($jtlProductI18n->getUrlPath())
                ? $jtlProductI18n->getName()
                : $jtlProductI18n->getUrlPath()
            );

            if (\Configuration::get('jtlconnector_truncate_desc')) {
                $limit = (int)\Configuration::get('PS_PRODUCT_SHORT_DESC_LIMIT');
                if ($limit <= 0) {
                    $limit = 800;
                }
                $translations[$langId]['description']       =
                    \Tools::substr($translations[$langId]['description'], 0, 21844);
                $translations[$langId]['description_short'] =
                    \Tools::substr($translations[$langId]['description_short'], 0, $limit);
                $translations[$langId]['meta_description']  =
                    \Tools::substr($translations[$langId]['meta_description'], 0, 512);
            }
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
        $activeCountries = \Country::getCountries($this->getPrestaContextLanguageId(), true);
        $jtlTaxes        = [];
        $prestaTaxes     = [];
        $conditions      = [];

        foreach ($taxRates as $taxRate) {
            if (\array_key_exists($this->getPrestaCountryIdFromIso($taxRate->getCountryIso()), $activeCountries)) {
                $jtlTaxes[] = $taxRate;
            }
        }

        foreach (\Tax::getTaxes() as $tax) {
            $prestaTaxes[\number_format((float)$tax['rate'], 3)] = $tax['id_tax'];
        }

        foreach ($jtlTaxes as $jtlTax) {
            if (!empty($jtlTax->getRate())) {
                $conditions[] = \sprintf(
                    'tr.id_country = %s AND tr.id_tax = %s',
                    $this->getPrestaCountryIdFromIso($jtlTax->getCountryIso()),
                    $prestaTaxes[\number_format($jtlTax->getRate(), 3)]
                );
            }
        }

        $sql = (new QueryBuilder())
            ->select('tr.id_tax_rules_group, COUNT(tr.id_tax_rules_group) AS hits')
            ->from('tax_rule', 'tr')
            ->where(\join(' OR ', $conditions))
            ->leftJoin('tax_rules_group', 'trg', 'trg.id_tax_rules_group = tr.id_tax_rules_group')
            ->where('trg.active = 1 AND trg.deleted = 0')
            ->groupBy('tr.id_tax_rules_group')
            ->orderBy('hits DESC');

        $result = $this->db->executeS($sql);

        return \is_array($result) ? $result[0]['id_tax_rules_group'] : null;
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
     * @param JtlProduct    $jtlProduct
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
                    if (!\is_int($value)) {
                        throw new \RuntimeException('Main_category_id Attribute value must be an integer');
                    }
                    $prestaProduct->id_category_default = $value;
                    break;
                case 'carriers':
                    if (!\is_string($value)) {
                        throw new \RuntimeException('Carriers Attribute value must be a comma separated string');
                    }
                    $carrierIds = \array_map('intval', \explode(',', $value));
                    if (!\in_array($carrierIds, ['', '0'])) {
                        $prestaProduct->setCarriers($carrierIds);
                    }
                    break;
                case 'products_status':
                    $prestaProduct->active = (bool)$value;
                    break;
                case 'online_only':
                    $prestaProduct->online_only = (bool)$value;
            }
        }

        $prices         = $jtlProduct->getPrices();
        $lastPriceEntry = \end($prices);

        if ($lastPriceEntry !== false) {
            $prestaProduct->price = \round($lastPriceEntry->getItems()[0]->getNetPrice(), 6);
        }

        $rrp = $jtlProduct->getRecommendedRetailPrice();
        if ($rrp > $prestaProduct->price) {
            $this->saveRecommendedRetailPriceAsFeature($prestaProduct, $rrp);
        }
    }


    /**
     * @param PrestaProduct $prestaProduct
     * @param float         $rrp
     * @return PrestaProduct
     */
    protected function saveRecommendedRetailPriceAsFeature(PrestaProduct $prestaProduct, float $rrp): PrestaProduct
    {
        $translations = [];
        $languages    = \Language::getLanguages();

        foreach ($languages as $language) {
            if (\is_array($language)) {
                $translations[$language['id_lang']] = [
                    'name' => 'recommended_retail_price',
                    'value' => $rrp
                ];
            }
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
        /** @var JtlProduct $model */
        $endpoint = $model->getId()->getEndpoint();
        if ($endpoint !== '') {
            [$art, $combiId] = Utils::explodeProductEndpoint($endpoint, 0);
            if (!empty($combiId)) {
                $obj = new \Product((int)$art);
            } else {
                $obj = new Combination((int)$combiId);
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
            ->setAvailable((int)$count + (int)$countVars)
            ->setControllerName($this->controllerName);
    }

    /**
     * @param JtlProduct    $jtlProduct
     * @param PrestaProduct $prestaProduct
     * @param int           $combiId
     *
     * @return void
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function createPrestaCombination(JtlProduct $jtlProduct, PrestaProduct $prestaProduct, int $combiId): void
    {
        $valueIds = [];
        foreach ($jtlProduct->getVariations() as $jtlVariation) {
            $groupId = $this->createPrestaAttributeGroup($jtlVariation);
            foreach ($jtlVariation->getValues() as $jtlVariationValue) {
                $valueIds[] = $this->createPrestaAttribute($jtlVariationValue, $groupId);
            }
        }

        $combi             = new Combination($combiId);
        $combi->price      = 0;
        $combi->id_product = $prestaProduct->id ?? throw new \RuntimeException('Product ID is missing');
        $combi->setAttributes($valueIds);
        $combi->save();
    }
}
