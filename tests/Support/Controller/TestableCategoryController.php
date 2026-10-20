<?php

declare(strict_types=1);

namespace Tests\Support\Controller;

use Category;
use Db;
use Jtl\Connector\Core\Model\Category as JtlCategory;
use Jtl\Connector\Core\Model\CategoryI18n as JtlCategoryI18n;
use jtl\Connector\Presta\Controller\CategoryController;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use Psr\Log\NullLogger;

/**
 * Overrides the constructor to avoid Db::getInstance() and provides fixed
 * language-lookup stubs. Exposes protected methods as public for unit testing.
 */
final class TestableCategoryController extends CategoryController
{
    public function __construct()
    {
        $this->db             = new Db();
        $this->logger         = new NullLogger();
        $this->controllerName = 'CategoryController';
    }

    protected function getJtlLanguageIsoFromLanguageId(string|int $langId): string
    {
        return match ((string)$langId) {
            '1'     => 'eng',
            '2'     => 'ger',
            default => 'eng',
        };
    }

    protected function getPrestaLanguageIdFromIso(string $languageIso): int
    {
        return match ($languageIso) {
            'deu', 'ger' => 2,
            default      => 1,
        };
    }

    protected function getPrestaContextShopId(): int
    {
        return 1;
    }

    public function exposeCreateJtlCategoryTranslation(array $data): JtlCategoryI18n
    {
        return $this->createJtlCategoryTranslation($data);
    }

    public function exposeCreateJtlCategoryTranslations(int $categoryId): array
    {
        return $this->createJtlCategoryTranslations($categoryId);
    }

    public function exposeCreatePrestaCategoryTranslations(JtlCategoryI18n ...$i18ns): array
    {
        return $this->createPrestaCategoryTranslations(...$i18ns);
    }

    public function exposeCreatePrestaCategory(JtlCategory $jtlCategory, Category $prestaCategory): Category
    {
        return $this->createPrestaCategory($jtlCategory, $prestaCategory);
    }

    public function injectDb(Db $db): void
    {
        $this->db = $db;
    }

    public function injectMapper(PrimaryKeyMapper $mapper): void
    {
        $this->mapper = $mapper;
    }
}
