<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\SpecificController;

use Feature;
use Jtl\Connector\Core\Model\SpecificI18n as JtlSpecificI18n;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableSpecificController;

final class CreateJtlSpecificI18nsTest extends TestCase
{
    private TestableSpecificController $controller;

    protected function setUp(): void
    {
        Feature::resetMock();
        $this->controller = new TestableSpecificController();
    }

    public function testCreateJtlSpecificI18nsEmptyI18nsReturnsEmptyArray(): void
    {
        $this->controller->setMockSpecificI18ns([]);
        $feature = new Feature(1);
        $result  = $this->controller->exposeCreateJtlSpecificI18ns($feature);

        self::assertSame([], $result);
    }

    public function testCreateJtlSpecificI18nsSingleEntry(): void
    {
        $this->controller->setMockSpecificI18ns([
            ['id_lang' => '1', 'name' => 'Farbe'],
        ]);
        $feature = new Feature(1);
        $result  = $this->controller->exposeCreateJtlSpecificI18ns($feature);

        self::assertCount(1, $result);
        self::assertInstanceOf(JtlSpecificI18n::class, $result[0]);
        self::assertSame('Farbe', $result[0]->getName());
        self::assertSame('eng', $result[0]->getLanguageIso());
    }

    public function testCreateJtlSpecificI18nsMultipleEntries(): void
    {
        $this->controller->setMockSpecificI18ns([
            ['id_lang' => '1', 'name' => 'Color'],
            ['id_lang' => '2', 'name' => 'Farbe'],
        ]);
        $feature = new Feature(1);
        $result  = $this->controller->exposeCreateJtlSpecificI18ns($feature);

        self::assertCount(2, $result);
        self::assertSame('Color', $result[0]->getName());
        self::assertSame('Farbe', $result[1]->getName());
    }
}
