<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\SpecificController;

use Jtl\Connector\Core\Model\SpecificValueI18n as JtlSpecificValueI18n;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableSpecificController;

final class CreateJtlSpecificValuesI18nsTest extends TestCase
{
    private TestableSpecificController $controller;

    protected function setUp(): void
    {
        $this->controller = new TestableSpecificController();
    }

    public function testCreateJtlSpecificValuesI18nsEmptyInputReturnsEmptyArray(): void
    {
        $result = $this->controller->exposeCreateJtlSpecificValuesI18ns([]);

        self::assertSame([], $result);
    }

    public function testCreateJtlSpecificValuesI18nsSingleEntry(): void
    {
        $data   = [['id_lang' => '1', 'value' => 'Red']];
        $result = $this->controller->exposeCreateJtlSpecificValuesI18ns($data);

        self::assertCount(1, $result);
        self::assertInstanceOf(JtlSpecificValueI18n::class, $result[0]);
        self::assertSame('Red', $result[0]->getValue());
        self::assertSame('eng', $result[0]->getLanguageIso());
    }

    public function testCreateJtlSpecificValuesI18nsMultipleEntries(): void
    {
        $data = [
            ['id_lang' => '1', 'value' => 'Rot'],
            ['id_lang' => '2', 'value' => 'Blau'],
        ];
        $result = $this->controller->exposeCreateJtlSpecificValuesI18ns($data);

        self::assertCount(2, $result);
        self::assertSame('Rot', $result[0]->getValue());
        self::assertSame('Blau', $result[1]->getValue());
    }

    public function testCreateJtlSpecificValuesI18nsEmptyValueString(): void
    {
        $data   = [['id_lang' => '1', 'value' => '']];
        $result = $this->controller->exposeCreateJtlSpecificValuesI18ns($data);

        self::assertSame('', $result[0]->getValue());
    }
}
