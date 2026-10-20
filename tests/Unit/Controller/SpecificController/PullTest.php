<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\SpecificController;

use Db;
use Feature;
use Jtl\Connector\Core\Model\QueryFilter;
use Jtl\Connector\Core\Model\Specific;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableSpecificController;

final class PullTest extends TestCase
{
    private TestableSpecificController $controller;

    protected function setUp(): void
    {
        Db::resetInstance();
        Feature::resetMock();
        $this->controller = new TestableSpecificController();
    }

    public function testPullWithEmptyDbResultReturnsEmptyArray(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([]);
        $this->controller->injectDb($db);

        $filter = new QueryFilter();
        $result = $this->controller->pull($filter);

        self::assertSame([], $result);
    }

    public function testPullWithSpecificIdButEmptyFeatureSkipsEntry(): void
    {
        // Feature with id=0 means "not found" → logger debug, continue
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([['id_feature' => '0']]);
        $this->controller->injectDb($db);

        $filter = new QueryFilter();
        $result = $this->controller->pull($filter);

        self::assertSame([], $result);
    }

    public function testPullWithNonZeroFeatureIdCreatesJtlSpecific(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturnOnConsecutiveCalls(
            [['id_feature' => '5']], // main pull query → one valid entry
            []                       // getPrestaSpecificValues → no values
        );
        $this->controller->setMockSpecificI18ns([]);
        $this->controller->injectDb($db);

        $filter = new QueryFilter();
        $result = $this->controller->pull($filter);

        self::assertCount(1, $result);
        self::assertInstanceOf(Specific::class, $result[0]);
        self::assertSame('5', $result[0]->getId()->getEndpoint());
    }
}
