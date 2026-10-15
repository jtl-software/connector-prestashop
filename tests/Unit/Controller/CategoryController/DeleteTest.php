<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CategoryController;

use Jtl\Connector\Core\Model\Category as JtlCategory;
use Jtl\Connector\Core\Model\Identity;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableCategoryController;

/**
 * delete: removes the PrestaShop category and returns the JtlCategory model.
 */
final class DeleteTest extends TestCase
{
    public function testReturnsThePassedModel(): void
    {
        $jtlCategory = (new JtlCategory())->setId(new Identity('3'));

        $result = (new TestableCategoryController())->delete($jtlCategory);

        self::assertSame($jtlCategory, $result);
    }
}
