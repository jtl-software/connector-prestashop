<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\SpecificController;

use FeatureValue;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\SpecificValue as JtlSpecificValue;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Controller\TestableSpecificController;

final class CreatePrestaSpecificValuesTest extends TestCase
{
    private TestableSpecificController $controller;

    protected function setUp(): void
    {
        FeatureValue::resetMock();
        $this->controller = new TestableSpecificController();
    }

    protected function tearDown(): void
    {
        FeatureValue::resetMock();
    }

    public function testCreatePrestaSpecificValuesSetsFeatureIdAndCustomFalse(): void
    {
        $prestaValue = new FeatureValue();
        $jtlValue    = new JtlSpecificValue();
        $jtlValue->setId(new Identity('', 0));

        $result = $this->controller->exposeCreatePrestaSpecificValues($prestaValue, $jtlValue, '5');

        self::assertSame(5, $result->id_feature);
        self::assertFalse($result->custom);
    }

    public function testCreatePrestaSpecificValuesNewValueCallsSave(): void
    {
        // Endpoint '' → isNew = true → save() is called, ObjectModel stub returns true
        $prestaValue = new FeatureValue();
        $jtlValue    = new JtlSpecificValue();
        $jtlValue->setId(new Identity('', 0));

        // Should not throw
        $result = $this->controller->exposeCreatePrestaSpecificValues($prestaValue, $jtlValue, '3');

        self::assertInstanceOf(FeatureValue::class, $result);
    }

    public function testCreatePrestaSpecificValuesExistingValueCallsUpdate(): void
    {
        // Endpoint non-empty → isNew = false → update() is called
        $prestaValue = new FeatureValue(42);
        $jtlValue    = new JtlSpecificValue();
        $jtlValue->setId(new Identity('42', 0));

        $result = $this->controller->exposeCreatePrestaSpecificValues($prestaValue, $jtlValue, '3');

        self::assertInstanceOf(FeatureValue::class, $result);
    }

    public function testCreatePrestaSpecificValuesExistingValueThrowsWhenUpdateFails(): void
    {
        FeatureValue::$mockUpdateResult = false;

        $prestaValue = new FeatureValue(42);
        $jtlValue    = new JtlSpecificValue();
        $jtlValue->setId(new Identity('42', 0));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error updating specific value with id: 42');

        $this->controller->exposeCreatePrestaSpecificValues($prestaValue, $jtlValue, '3');
    }

    public function testCreatePrestaSpecificValuesNewValueThrowsWhenSaveFails(): void
    {
        FeatureValue::$mockSaveResult = false;

        $prestaValue = new FeatureValue();
        $jtlValue    = new JtlSpecificValue();
        $jtlValue->setId(new Identity('', 0));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error uploading specific value with id: ');

        $this->controller->exposeCreatePrestaSpecificValues($prestaValue, $jtlValue, '3');
    }
}
