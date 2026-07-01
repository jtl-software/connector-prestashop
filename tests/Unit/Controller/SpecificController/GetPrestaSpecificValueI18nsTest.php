<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\SpecificController;

use Db;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\SpecificControllerWithRealQueries;

final class GetPrestaSpecificValueI18nsTest extends TestCase
{
    protected function setUp(): void
    {
        Db::resetInstance();
    }

    public function testGetPrestaSpecificValueI18nsReturnsEmptyArrayWhenNoResults(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([]);

        $realController = new SpecificControllerWithRealQueries();
        $realController->injectDb($db);

        $result = $realController->exposeGetPrestaSpecificValueI18ns(10);

        self::assertSame([], $result);
    }

    public function testGetPrestaSpecificValueI18nsReturnsDataFromDb(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([
            ['id_lang' => '1', 'value' => 'Blue'],
            ['id_lang' => '2', 'value' => 'Blau'],
        ]);

        $realController = new SpecificControllerWithRealQueries();
        $realController->injectDb($db);

        $result = $realController->exposeGetPrestaSpecificValueI18ns(10);

        self::assertCount(2, $result);
        self::assertSame('Blue', $result[0]['value']);
        self::assertSame('Blau', $result[1]['value']);
    }
}
