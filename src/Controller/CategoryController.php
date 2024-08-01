<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Controller;

use Category as PrestaCategory;
use Jtl\Connector\Core\Controller\DeleteInterface;
use Jtl\Connector\Core\Controller\PullInterface;
use Jtl\Connector\Core\Controller\PushInterface;
use Jtl\Connector\Core\Definition\IdentityType;
use Jtl\Connector\Core\Model\Category as JtlCategory;
use Jtl\Connector\Core\Model\CategoryI18n as JtlCategoryI18n;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\QueryFilter;
use Jtl\Connector\Core\Model\AbstractModel;
use Jtl\Connector\Core\Model\Statistic;
use jtl\Connector\Presta\Utils\QueryBuilder;
use PrestaShopDatabaseException;
use PrestaShopException;

class CategoryController extends AbstractController implements PullInterface, PushInterface, DeleteInterface
{
    /**
     * @param QueryFilter $queryFilter
     * @return array|AbstractModel[]
     * @throws PrestaShopDatabaseException
     */
    public function pull(QueryFilter $queryFilter): array
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->setUsePrefix(false);

        $sql = $queryBuilder
            ->select('c.*')
            ->from(\_DB_PREFIX_ . 'category', 'c')
            ->leftJoin(self::CATEGORY_LINKING_TABLE, 'l', 'c.id_category = l.endpoint_id')
            ->where('l.host_id IS NULL AND c.id_parent != 0')
            ->orderBy('c.nleft')
            ->limit((int)$this->db->escape((string)$queryFilter->getLimit()));

        $prestaCategories = $this->db->executeS($sql);

        $jtlCategories = [];

        if (\is_array($prestaCategories) && !empty($prestaCategories)) {
            foreach ($prestaCategories as $prestaCategory) {
                $jtlCategory     = $this->createJtlCategory($prestaCategory);
                $jtlCategories[] = $jtlCategory;
            }
        }

        return $jtlCategories;
    }

    /**
     * @param array<string, string|int> $prestaCategory
     * @return JtlCategory
     * @throws PrestaShopDatabaseException
     * @throws \RuntimeException
     */
    protected function createJtlCategory(array $prestaCategory): JtlCategory
    {
        $isActive = \is_int($prestaCategory['active'])
            ? (bool)$prestaCategory['active']
            : throw new \RuntimeException('active must be an integer');

        $level = \is_int($prestaCategory['level_depth'])
            ? $prestaCategory['level_depth']
            : throw new \RuntimeException('level_depth must be an integer');

        $prestaCategoryId = \is_int($prestaCategory['id_category'])
            ? $prestaCategory['id_category']
            : throw new \RuntimeException('id_category must be an integer');

        $prestaParentCategoryId = \is_int($prestaCategory['id_parent'])
            ? $prestaCategory['id_parent']
            : throw new \RuntimeException('id_parent must be an integer');

        $prestaRootCategoryId = \is_int(PrestaCategory::getRootCategory()->id)
            ? PrestaCategory::getRootCategory()->id
            : throw new \RuntimeException('Root category id not found');

        $jtlCategory = (new JtlCategory())
            ->setId(new Identity((string)$prestaCategoryId))
            ->setIsActive($isActive)
            ->setLevel($level)
            ->setParentCategoryId(
                ($prestaParentCategoryId === $prestaRootCategoryId || $prestaParentCategoryId == 2)
                ? new Identity('')
                : new Identity((string)$prestaParentCategoryId)
            );


        $jtlCategoryI18ns = $this->createJtlCategoryTranslations($prestaCategoryId);

        $jtlCategory
            ->setI18ns(...$jtlCategoryI18ns);

        return $jtlCategory;
    }

    /**
     * @param array $prestaCategoryI18n
     * @phpstan-param array{
     *     id_category: int,
     *     id_shop: int,
     *     id_lang: int,
     *     name: string,
     *     description: string,
     *     additional_description: string,
     *     link_rewrite: string,
     *     meta_title: string,
     *     meta_keywords: string,
     *     meta_description: string
     * } $prestaCategoryI18n
     * @return JtlCategoryI18n
     * @throws PrestaShopDatabaseException
     */
    protected function createJtlCategoryTranslation(array $prestaCategoryI18n): JtlCategoryI18n
    {
        return (new JtlCategoryI18n())
            ->setName((string)$prestaCategoryI18n['name'])
            ->setTitleTag((string)$prestaCategoryI18n['meta_title'])
            ->setDescription((string)$prestaCategoryI18n['description'])
            ->setMetaDescription((string)$prestaCategoryI18n['meta_description'])
            ->setMetaKeywords((string)$prestaCategoryI18n['meta_keywords'])
            ->setLanguageIso($this->getJtlLanguageIsoFromLanguageId($prestaCategoryI18n['id_lang']));
    }

    /**
     * @param int $prestaCategoryId
     * @return array<JtlCategoryI18n>
     * @throws PrestaShopDatabaseException|PrestaShopException|\RuntimeException
     */
    protected function createJtlCategoryTranslations(int $prestaCategoryId): array
    {

        $shopId = $this->getPrestaContextShopId();
        $sql    = (new QueryBuilder())
            ->select('cl.*')
            ->from('category_lang', 'cl')
            ->leftJoin('lang', 'l', 'l.id_lang = cl.id_lang')
            ->where("cl.id_category = $prestaCategoryId AND cl.id_shop = $shopId");

        $results = $this->db->executeS($sql);

        $i18ns = [];

        if (\is_array($results) && !empty($results)) {
            foreach ($results as $result) {
                $i18ns[] = $this->createJtlCategoryTranslation($result);
            }
        }

        return $i18ns;
    }

    /**
     * @param AbstractModel $jtlCategory
     * @return AbstractModel
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     * @throws \RuntimeException
     */
    public function push(AbstractModel $jtlCategory): AbstractModel
    {
        /** @var JtlCategory $jtlCategory */
        $endpoint = $jtlCategory->getId()->getEndpoint();
        $isNew    = $endpoint === '';

        if (!$isNew) {
            $prestaCategory = $this->createPrestaCategory($jtlCategory, new PrestaCategory((int)$endpoint));
            if (!$prestaCategory->update()) {
                throw new \RuntimeException('Error updating category' . $jtlCategory->getI18ns()[0]->getName());
            }

            return $jtlCategory;
        }

        $prestaCategory = $this->createPrestaCategory($jtlCategory, new PrestaCategory());
        if (!$prestaCategory->add()) {
            if ($prestaCategory->id) {
                try {
                    // explicitly delete category to prevent broken category
                    $prestaCategory->delete();
                } catch (\Exception) {
                    // ignore
                }
            }
            throw new \RuntimeException('Error uploading category' . $jtlCategory->getI18ns()[0]->getName());
        }

        $this->mapper->save(IdentityType::CATEGORY, (string)$prestaCategory->id, $jtlCategory->getId()->getHost());

        return $jtlCategory;
    }

    /**
     * @param JtlCategory    $jtlCategory
     * @param PrestaCategory $prestaCategory
     *
     * @return PrestaCategory
     * @throws PrestaShopDatabaseException
     * @throws \RuntimeException
     */
    protected function createPrestaCategory(JtlCategory $jtlCategory, PrestaCategory $prestaCategory): PrestaCategory
    {
        $translations              = $this->createPrestaCategoryTranslations(...$jtlCategory->getI18ns());
        $prestaRootCategoryId      = \is_int(PrestaCategory::getRootCategory()->id)
            ? PrestaCategory::getRootCategory()->id
            : throw new \RuntimeException('Root category id not found');
        $prestaCategory->active    = $jtlCategory->getIsActive();
        $prestaCategory->position  = $jtlCategory->getSort();
        $prestaCategory->id_parent =
            empty($jtlCategory->getParentCategoryId()->getEndpoint())
                ? $prestaRootCategoryId
                : (int)$jtlCategory->getParentCategoryId()->getEndpoint();

        foreach ($translations as $key => $translation) {
            $prestaCategory->name             = [];
            $prestaCategory->description      = [];
            $prestaCategory->meta_description = [];
            $prestaCategory->meta_keywords    = [];
            $prestaCategory->link_rewrite     = [];

            $prestaCategory->name[$key]             = $translation['name'];
            $prestaCategory->description[$key]      = $translation['description'];
            $prestaCategory->meta_description[$key] = $translation['metaDescription'];
            $prestaCategory->meta_keywords[$key]    = $translation['metaKeywords'];
            $prestaCategory->link_rewrite[$key]     = $translation['url'];
        }

        return $prestaCategory;
    }

    /**
     * @param JtlCategoryI18n ...$jtlCategoryI18ns
     * @return array<
     *    int, array{
     *     name: string,
     *     description: string,
     *     metaDescription: string,
     *     metaKeywords: string,
     *     url: string
     *  }
     * >
     * @throws PrestaShopDatabaseException
     * @throws \RuntimeException
     */
    protected function createPrestaCategoryTranslations(JtlCategoryI18n ...$jtlCategoryI18ns): array
    {
        $translations = [];
        foreach ($jtlCategoryI18ns as $jtlCategoryI18n) {
            $languageIso = $this->getPrestaLanguageIdFromIso($jtlCategoryI18n->getLanguageIso());
            $name        = \preg_replace('/[<>;=#{}]/', '_', $jtlCategoryI18n->getName());
            $url         = \Tools::str2url(empty($jtlCategoryI18n->getUrlPath())
                ? $jtlCategoryI18n->getName()
                : $jtlCategoryI18n->getUrlPath());

            $langId                                   = $languageIso;
            $translations[$langId]['name']            = \is_string($name)
                ? $name
                : throw new \RuntimeException('Name must be a string');
            $translations[$langId]['description']     = $jtlCategoryI18n->getDescription();
            $translations[$langId]['metaDescription'] = $jtlCategoryI18n->getMetaDescription();
            $translations[$langId]['metaKeywords']    = $jtlCategoryI18n->getMetaKeywords();
            $translations[$langId]['url']             = \is_string($url)
                ? $url
                : throw new \RuntimeException('Url must be a string');

            if (\Configuration::get('jtlconnector_truncate_desc')) {
                $translations[$langId]['description']     =
                    \mb_substr($translations[$langId]['description'], 0, 21844);
                $translations[$langId]['metaDescription'] =
                    \mb_substr($translations[$langId]['metaDescription'], 0, 512);
            }
        }

        return $translations;
    }

    /**
     * @param AbstractModel $model
     * @return AbstractModel
     * @throws PrestaShopException
     */
    public function delete(AbstractModel $model): AbstractModel
    {
        /** @var JtlCategory $model */
        $category = new PrestaCategory((int)$model->getId()->getEndpoint());

        $category->delete();

        return $model;
    }


    /**
     * @return Statistic
     */
    public function statistic(): Statistic
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->setUsePrefix(false);

        $sql = $queryBuilder
            ->select('COUNT(*)')
            ->from(\_DB_PREFIX_ . 'category', 'c')
            ->leftJoin(self::CATEGORY_LINKING_TABLE, 'l', 'c.id_category = l.endpoint_id')
            ->where('l.host_id IS NULL AND c.id_parent != 0 AND c.is_root_category = 0');

        $result = $this->db->getValue($sql);

        return (new Statistic())
            ->setAvailable((int)$result)
            ->setControllerName($this->controllerName);
    }
}
