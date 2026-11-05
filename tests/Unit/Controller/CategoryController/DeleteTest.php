<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CategoryController;

use Jtl\Connector\Core\Model\Category as JtlCategory;
use Jtl\Connector\Core\Model\Identity;
use ObjectModel;
use PHPUnit\Framework\TestCase;
use Tests\Support\AfterHook;
use Tests\Support\Controller\TestableCategoryController;

/**
 * delete: removes the PrestaShop category and returns the JtlCategory model.
 */
final class DeleteTest extends TestCase
{
    protected function tearDown(): void
    {
        ObjectModel::resetObjectModelMock();
    }

    public function testReturnsThePassedModel(): void
    {
        $jtlCategory = (new JtlCategory())->setId(new Identity('3'));

        [$result] = (new TestableCategoryController())->delete($jtlCategory);

        self::assertSame($jtlCategory, $result);
    }

    public function testDeletesWithoutStackFramesThatPsEventbusCannotRead(): void
    {
        ObjectModel::$mockAfterHook = AfterHook::psEventbusErrorHandler();
        $jtlCategory                = (new JtlCategory())->setId(new Identity('3', 5001));

        self::assertSame([$jtlCategory], (new TestableCategoryController())->delete($jtlCategory));
    }
}
