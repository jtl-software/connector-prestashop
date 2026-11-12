<?php

declare(strict_types=1);

namespace Tests\Support\Controller;

use Db;
use Jtl\Connector\Core\Model\Category as JtlCategory;
use Jtl\Connector\Core\Model\CategoryI18n as JtlCategoryI18n;
use jtl\Connector\Presta\Controller\CategoryController;
use Psr\Log\NullLogger;

/**
 * Stubs createJtlCategoryTranslations so that createJtlCategory() can be
 * exercised in isolation without a second DB call. Used for pull and
 * createJtlCategory tests.
 */
final class TestableCategoryControllerFull extends CategoryController
{
    /** @var JtlCategoryI18n[] */
    private array $stubbedI18ns = [];

    public function __construct()
    {
        $this->db             = new Db();
        $this->logger         = new NullLogger();
        $this->controllerName = 'CategoryController';
    }

    public function stubI18ns(JtlCategoryI18n ...$i18ns): void
    {
        $this->stubbedI18ns = $i18ns;
    }

    protected function createJtlCategoryTranslations(int $id): array
    {
        return $this->stubbedI18ns;
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

    public function injectDb(Db $db): void
    {
        $this->db = $db;
    }

    public function exposeCreateJtlCategory(array $data): JtlCategory
    {
        return $this->createJtlCategory($data);
    }
}
