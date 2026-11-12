<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\SpecificController;

use Feature;
use Jtl\Connector\Core\Model\Specific;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableSpecificController;

final class CreateJtlSpecificTest extends TestCase
{
    private TestableSpecificController $controller;

    protected function setUp(): void
    {
        Feature::resetMock();
        $this->controller = new TestableSpecificController();
    }

    public function testCreateJtlSpecificReturnsCorrectModel(): void
    {
        $this->controller->setMockSpecificI18ns([['id_lang' => '1', 'name' => 'Color']]);
        $this->controller->setMockSpecificValueI18ns([]);

        $feature       = new Feature(5);
        $feature->name = [1 => 'Color'];

        $result = $this->controller->exposeCreateJtlSpecific($feature);

        self::assertInstanceOf(Specific::class, $result);
        self::assertSame('5', $result->getId()->getEndpoint());
    }
}
