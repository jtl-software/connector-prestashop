<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Controller;

use AttributeGroup;
use Combination;
use Context;
use Exception;
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
use Jtl\Connector\Core\Model\TranslatableAttribute as JtlTranslatableAttribute;
use Jtl\Connector\Core\Model\TranslatableAttributeI18n as JtlTranslatableAttributeI18n;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use jtl\Connector\Presta\Utils\QueryBuilder;
use jtl\Connector\Presta\Utils\Utils;
use Product as PrestaProduct;

class ProductController extends AbstractController implements PullInterface, PushInterface, DeleteInterface
{
    public const
        JTL_ATTRIBUTE_ACTIVE = 'active',
        JTL_ATTRIBUTE_ONLINE_ONLY = 'online_only',
        JTL_ATTRIBUTE_MAIN_CATEGORY_ID = 'main_category_id',
        JTL_ATTRIBUTE_CARRIERS = 'carriers';

    protected array $jtlSpecialAttributes = [
        self::JTL_ATTRIBUTE_ACTIVE,
        self::JTL_ATTRIBUTE_ONLINE_ONLY,
        self::JTL_ATTRIBUTE_MAIN_CATEGORY_ID,
        self::JTL_ATTRIBUTE_CARRIERS
    ];

    private PrimaryKeyMapper $mapper;


    public function __construct(PrimaryKeyMapper $mapper)
    {
        $this->mapper = $mapper;
        parent::__construct();
    }

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
                    (int) $prestaProductId['id_product_attribute'],
                    $prestaProduct
                );
            } else {
                $jtlProducts[] = $product;
            }
        }

        return $jtlProducts;
    }

    protected function getNotLinkedEntities(
        QueryFilter $queryFilter,
        string      $linkingTable,
        string      $prestaTable,
        string      $columns,
        ?string     $fromDate = null
    ): array {
        $foo = parent::getNotLinkedEntities($queryFilter, $linkingTable, $prestaTable, $columns, $fromDate);

        if (\count($foo) < $queryFilter->getLimit()) {
            $sql = 'SELECT p.id_product as id_product, p.id_product_attribute as id_product_attribute FROM ps_product_attribute p
                LEFT JOIN ps_product pr ON pr.id_product = p.id_product
                LEFT JOIN jtl_connector_link_product l 
                ON CONCAT(p.id_product, "_", p.id_product_attribute) = l.endpoint_id
                WHERE l.host_id IS NULL AND p.id_product > 0
                LIMIT 100';

            $bar = $this->db->executeS($sql);

            $foo = \array_merge($foo, $bar);
        }

        return $foo;
    }

    /**
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

    protected function createJtlProductsFromVariation(
        JtlProduct    $product,
        int           $variationId,
        PrestaProduct $presta
    ): JtlProduct {
        $comb       = new Combination($variationId);
        $attributes = $presta->getAttributesGroups(Context::getContext()->language->id, $variationId);

        return (new JtlProduct())
            ->setId(new Identity($product->getId()->getEndpoint() . '_' . (string) $variationId))
            ->setManufacturerId($product->getManufacturerId())
            ->setCreationDate($product->getCreationDate())
            ->setEan($comb->ean13)
            ->setIsbn($comb->isbn)
            ->setHeight($product->getHeight())
            ->setIsMasterProduct(false)
            ->setMasterProductId($product->getId())
            ->setModified($product->getModified())
            ->setShippingWeight($product->getShippingWeight())
            ->setSku($product->getSku())
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

    protected function createJtlProductTranslation(array $prestaProductI18n): JtlProductI18n
    {
        return (new JtlProductI18n())
            ->setName($prestaProductI18n['name'])
            ->setTitleTag($prestaProductI18n['meta_title'])
            ->setDescription($prestaProductI18n['description'])
            ->setShortDescription($prestaProductI18n['description_short'])
            ->setMetaDescription($prestaProductI18n['meta_description'])
            ->setLanguageIso($this->getJtlLanguageIsoFromLanguageId((int)$prestaProductI18n['id_lang']));
    }

    /**
     * @throws \PrestaShopDatabaseException
     * @throws TranslatableAttributeException
     * @throws \JsonException
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

    protected function createJtlProductCategories(PrestaProduct $prestaProduct): array
    {
        $jtlProductCategories = [];

        foreach ($prestaProduct->getCategories() as $category) {
            $host                   = $this->mapper->getHostId(IdentityType::CATEGORY, (string)$category);
            $jtlProductCategories[] = (new JtlProductCategory())->setCategoryId(
                new Identity((string)$category, $host ?? '')
            );
        }

        return $jtlProductCategories;
    }

    /**
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

    protected function createJtlProductVariation(
        int $prestaVariationId
    ): JtlProductVariation {
        return (new JtlProductVariation())
            ->setId(new Identity((string)$prestaVariationId))
            ->setI18ns(...$this->createJtlProductVariationI18ns($prestaVariationId));
    }

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

    protected function createJtlProductVariationValues(int $variationId, array $prestaVariations): array
    {
        $languages                      = \Language::getLanguages();
        $prestaVariationValuesByLangIds = [];
        $jtlVariationValues             = [];

        foreach ($languages as $language) {
            $langId = $language['id_lang'];
            foreach ($prestaVariations as $prestaVariation) {
                if ((int) $prestaVariation['id_attribute_group'] === $variationId) {
                    $comb = new \ProductAttribute(
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

    public function postPush($data)
    {
        $masterId = $data->getMasterProductId()->getEndpoint();

        if (empty($masterId)) {
            $this->db->execute(
                'UPDATE ' . \_DB_PREFIX_ . 'product SET unit_price_ratio=' . $data->getBasePriceDivisor(
                ) . ' WHERE id_product=' . $data->getId()->getEndpoint()
            );
            $this->db->execute(
                'UPDATE ' . \_DB_PREFIX_ . 'product_shop SET unit_price_ratio=' . $data->getBasePriceDivisor(
                ) . ' WHERE id_product=' . $data->getId()->getEndpoint()
            );
        }

        \Product::flushPriceCache();
    }

    /**
     * @param \jtl\Connector\Model\Product $data
     *
     * @return mixed
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function push(AbstractModel $jtlProduct): AbstractModel
    {
//        /** @var \Product $product */
//
//        if (isset(static::$idCache[$data->getMasterProductId()->getHost()])) {
//            $data->getMasterProductId()->setEndpoint(static::$idCache[$data->getMasterProductId()->getHost()]);
//        }
//
//        $masterId = $data->getMasterProductId()->getEndpoint();
//
//        $exists = false;
//
//        try {
//            if (empty($masterId)) {
//                $product = $this->mapper->toEndpoint($data);
//
//                $exists = $product->id > 0;
//
//                $product->save();
//
//                $id = $product->id;
//            } else {
//                list($productId, $combiId) = Utils::explodeProductEndpoint($data->getId()->getEndpoint());
//
//                $product = new \Product($masterId);
//
//                $exists = $product->id > 0;
//
//                $minOrder = \ceil($data->getMinimumOrderQuantity());
//                $minOrder = $minOrder < 1 ? 1 : $minOrder;
//
//                if (!empty($combiId)) {
//                    $product->updateAttribute(
//                        $combiId,
//                        null,
//                        null,
//                        $data->getShippingWeight(),
//                        null,
//                        null,
//                        null,
//                        $data->getSku(),
//                        $data->getEan(),
//                        null,
//                        null,
//                        $data->getUpc(),
//                        $minOrder
//                    );
//
//                    $id = $data->getId()->getEndpoint();
//                } else {
//                    $combiId = $product->addAttribute(
//                        null,
//                        $data->getShippingWeight(),
//                        null,
//                        null,
//                        null,
//                        $data->getSku(),
//                        $data->getEan(),
//                        null,
//                        null,
//                        $data->getUpc(),
//                        $minOrder
//                    );
//
//                    $id = $data->getMasterProductId()->getEndpoint() . '_' . $combiId;
//                }
//
//                $combi = new Combination($combiId);
//
//                $allowedGroupTypes = [
//                    ProductVariation::TYPE_RADIO,
//                    ProductVariation::TYPE_SELECT
//                ];
//
//                $valIds = [];
//                foreach ($data->getVariations() as $variation) {
//                    $groupType       = \in_array(
//                        $variation->getType(),
//                        $allowedGroupTypes
//                    ) ? $variation->getType() : ProductVariation::TYPE_SELECT;
//                    $attrGrpId       = null;
//                    $attrPublicNames = [];
//                    $attrNames       = [];
//                    foreach ($variation->getI18ns() as $varI18n) {
//                        $langId  = Utils::getInstance()->getLanguageIdByIso($varI18n->getLanguageISO());
//                        $varName = $varI18n->getName();
//                        if (!empty($varName)) {
//                            $attrNames[$langId]       = \sprintf('%s (%s)', $varName, \ucfirst($groupType));
//                            $attrPublicNames[$langId] = $varName;
//                        }
//
//                        if ($langId == Context::getContext()->language->id) {
//                            $sql       = \sprintf(
//                                'SELECT id_attribute_group FROM %sattribute_group_lang WHERE name = "%s"',
//                                \_DB_PREFIX_,
//                                $attrNames[$langId]
//                            );
//                            $attrGrpId = $this->db->getValue($sql);
//                        }
//                    }
//
//                    if (
//                        \in_array($attrGrpId, [false, null], true) || \in_array(
//                            $variation->getType(),
//                            $allowedGroupTypes
//                        )
//                    ) {
//                        $attrGrp              = new AttributeGroup($attrGrpId);
//                        $attrGrp->name        = $attrNames;
//                        $attrGrp->public_name = $attrPublicNames;
//                        $attrGrp->group_type  = $groupType;
//                        $attrGrp->save();
//                        $attrGrpId = $attrGrp->id;
//                    }
//
//                    foreach ($variation->getValues() as $value) {
//                        $valNames = [];
//                        foreach ($value->getI18ns() as $valI18n) {
//                            $langId = Utils::getInstance()->getLanguageIdByIso($valI18n->getLanguageISO());
//
//                            $valName = $valI18n->getName();
//
//                            if (!empty($valName)) {
//                                $valNames[$langId] = $valName;
//                            }
//
//                            if ($langId == Context::getContext()->language->id) {
//                                $valId = $this->db->getValue(
//                                    '
//                              SELECT l.id_attribute
//                              FROM ' . \_DB_PREFIX_ . 'attribute_lang l
//                              LEFT JOIN ' . \_DB_PREFIX_ . 'attribute a ON a.id_attribute = l.id_attribute
//                              WHERE l.name="' . $valName . '" && a.id_attribute_group = ' . $attrGrpId
//                                );
//                            }
//                        }
//
//                        $val                     = new Attribute($valId);
//                        $val->name               = $valNames;
//                        $val->id_attribute_group = $attrGrpId;
//                        $val->position           = $value->getSort();
//
//                        $val->save();
//
//                        $valId = $val->id;
//
//                        $valIds[] = $valId;
//                    }
//                }
//
//                $oldVariantImages = $combi->getWsImages();
//                $combi->price     = 0;
//                $combi->setAttributes($valIds);
//                $combi->setWsImages($oldVariantImages);
//                $combi->save();
//
//                $product->checkDefaultAttributes();
//            }
//
//            $data->getId()->setEndpoint($id);
//
//            if ($id) {
//                $data->getStockLevel()->getProductId()->setEndpoint($id);
//                $stock = new ProductStockLevel();
//                $stock->pushData($data->getStockLevel(), $product);
//
//                foreach ($data->getPrices() as $price) {
//                    $price->getProductId()->setEndpoint($id);
//                }
//
//                $price = new ProductPrice();
//                foreach ($data->getPrices() as $priceData) {
//                    $price->pushData($priceData);
//                }
//
//                $specialPrices = new ProductSpecialPrice();
//                $specialPrices->pushData($data);
//
//                $categories = new Product2Category();
//                $categories->pushData($data);
//
//                if (isset($product) && $data->getMasterProductId()->getHost() === 0) {
//                    ProductAttr::getInstance()->pushData($data, $product);
//                    ProductSpecific::getInstance()->pushData($data, $product);
//                    $this->pushSpecialAttributes($data, $product);
//                }
//            }
//
//            static::$idCache[$data->getId()->getHost()] = $id;
//
//            return $data;
//        } catch (\Exception $e) {
//            if (!empty($product) && !$exists) {
//                $product->delete();
//            }
//            throw $e;
//        }
        return new JtlProduct();
    }

    /**
     * @param \jtl\Connector\Model\Product $data
     * @param \Product                     $product
     *
     * @throws PrestaShopException
     */
    private function pushSpecialAttributes($data, $product)
    {
        /** @var \jtl\Connector\Model\Product $data */

        $specialAttributes = ProductAttr::getSpecialAttributes();

        $foundSpecialAttributes = [];
        $tags                   = [];
        foreach ($data->getAttributes() as $attribute) {
            foreach ($attribute->getI18ns() as $i18n) {
                if ($i18n->getName() === ProductAttr::TAGS) {
                    $id        = Utils::getInstance()->getLanguageIdByIso($i18n->getLanguageISO());
                    $tags[$id] = \explode(',', $i18n->getValue());
                }

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
                    foreach ($data->getCategories() as $product2Category) {
                        if (
                            $product2Category->getCategoryId()->getHost() === $value
                            && $product2Category->getCategoryId()->getEndpoint() !== ''
                        ) {
                            $value = (int)$product2Category->getCategoryId()->getEndpoint();
                        }
                    }
                    break;
                case 'carriers':
                    $carriers = \explode(',', $value);
                    if (!\in_array($carriers, ['', '0'], true)) {
                        $product->setCarriers($carriers);
                    }
                    break;
            }


            $product->{$specialAttributes[$key]} = $value;
        }

        \Tag::deleteTagsForProduct($product->id_product);
        foreach ($tags as $languageId => $tagList) {
            \Tag::addTags($languageId, $product->id_product, $tagList);
        }

        $prices         = $data->getPrices();
        $product->price = \round(\end($prices)->getItems()[0]->getNetPrice(), 6);

        $rrp = $data->getRecommendedRetailPrice();
        if ($rrp > $product->price) {
            $this->saveRecommendedRetailPriceAsFeature($product, $rrp);
        }

        $product->save();
    }

    /**
     * @param \Product $prestaProduct
     * @param float    $rrp
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function saveRecommendedRetailPriceAsFeature(\Product $prestaProduct, float $rrp)
    {
        $defaultLanguageId = Context::getContext()->language->id;

        $translations = [];
        foreach (Utils::getInstance()->getLanguages() as $language) {
            $translations[$language['id_lang']] = [
                'name'  => ProductAttr::RECOMMENDED_RETAIL_PRICE,
                'value' => $rrp
            ];
        }

        $this->productAttrMapper->saveCustomAttribute($prestaProduct, $defaultLanguageId, $translations, true);
    }

    /**
     * @param \jtl\Connector\Model\Product $data
     *
     * @return mixed
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     * @throws Exception
     */
    public function delete(AbstractModel $model): AbstractModel
    {
        $endpoint = $data->getId()->getEndpoint();
        if ($endpoint !== '') {
            $isCombi = \strpos($data->getId()->getEndpoint(), '_') !== false;
            if (!$isCombi) {
                $obj = new \Product($endpoint);
            } else {
                list($productId, $combiId) = \explode('_', $data->getId()->getEndpoint());
                $obj = new Combination($combiId);
            }

            $obj->delete();
        }

        return $data;
    }

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
