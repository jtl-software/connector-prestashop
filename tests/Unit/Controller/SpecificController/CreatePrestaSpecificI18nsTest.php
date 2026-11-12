<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\SpecificController;

use Jtl\Connector\Core\Model\SpecificI18n as JtlSpecificI18n;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableSpecificController;

final class CreatePrestaSpecificI18nsTest extends TestCase
{
    private TestableSpecificController $controller;

    protected function setUp(): void
    {
        $this->controller = new TestableSpecificController();
    }

    public function testCreatePrestaSpecificI18nsEmptyInputReturnsEmptyArray(): void
    {
        $result = $this->controller->exposeCreatePrestaSpecificI18ns();

        self::assertSame([], $result);
    }

    public function testCreatePrestaSpecificI18nsMapsNameCorrectly(): void
    {
        $i18n   = (new JtlSpecificI18n())->setName('Material')->setLanguageIso('eng');
        $result = $this->controller->exposeCreatePrestaSpecificI18ns($i18n);

        // getPrestaLanguageIdFromIso always returns 1 in the testable subclass
        self::assertArrayHasKey(1, $result);
        self::assertSame('Material', $result[1]['name']);
    }

    public function testCreatePrestaSpecificI18nsMultipleI18ns(): void
    {
        $eng = (new JtlSpecificI18n())->setName('Color')->setLanguageIso('eng');
        $ger = (new JtlSpecificI18n())->setName('Farbe')->setLanguageIso('ger');

        $result = $this->controller->exposeCreatePrestaSpecificI18ns($eng, $ger);

        // Both resolve to langId=1 via the stub; second write overwrites first
        self::assertArrayHasKey(1, $result);
    }
}
