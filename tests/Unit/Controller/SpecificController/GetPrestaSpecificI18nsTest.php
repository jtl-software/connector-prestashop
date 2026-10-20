<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\SpecificController;

use Db;
use Feature;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\SpecificControllerWithRealQueries;

final class GetPrestaSpecificI18nsTest extends TestCase
{
    protected function setUp(): void
    {
        Db::resetInstance();
    }

    public function testGetPrestaSpecificI18nsReturnsEmptyArrayWhenNoResults(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([]);

        $realController = new SpecificControllerWithRealQueries();
        $realController->injectDb($db);

        $feature = new Feature(5);
        $result  = $realController->exposeGetPrestaSpecificI18ns($feature);

        self::assertSame([], $result);
    }

    public function testGetPrestaSpecificI18nsReturnsDataFromDb(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([
            ['id_lang' => '1', 'name' => 'Color'],
        ]);

        $realController = new SpecificControllerWithRealQueries();
        $realController->injectDb($db);

        $feature = new Feature(5);
        $result  = $realController->exposeGetPrestaSpecificI18ns($feature);

        self::assertCount(1, $result);
        self::assertSame('Color', $result[0]['name']);
    }
}
