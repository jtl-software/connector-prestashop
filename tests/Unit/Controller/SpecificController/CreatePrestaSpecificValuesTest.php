<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\SpecificController;

use FeatureValue;
use Jtl\Connector\Core\Definition\IdentityType;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\SpecificValue as JtlSpecificValue;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use ObjectModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Controller\TestableSpecificController;

final class CreatePrestaSpecificValuesTest extends TestCase
{
    private TestableSpecificController $controller;

    private PrimaryKeyMapper&MockObject $mapper;

    protected function setUp(): void
    {
        FeatureValue::resetMock();
        ObjectModel::resetObjectModelMock();
        ObjectModel::$mockNextInsertId = 21;
        $this->controller              = new TestableSpecificController();
        $this->mapper                  = $this->createMock(PrimaryKeyMapper::class);
        $this->controller->injectMapper($this->mapper);
    }

    protected function tearDown(): void
    {
        FeatureValue::resetMock();
        ObjectModel::resetObjectModelMock();
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

    public function testCreatePrestaSpecificValuesNewValueIsAddedAndLinked(): void
    {
        // Endpoint '' → isNew = true → add(), then the value is linked right away
        $this->mapper->expects(self::once())->method('save')->with(IdentityType::SPECIFIC_VALUE, '21', 6002);
        $prestaValue = new FeatureValue();
        $jtlValue    = new JtlSpecificValue();
        $jtlValue->setId(new Identity('', 6002));

        $result = $this->controller->exposeCreatePrestaSpecificValues($prestaValue, $jtlValue, '3');

        self::assertSame(21, $result->id);
        self::assertSame('21', $jtlValue->getId()->getEndpoint());
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

    public function testCreatePrestaSpecificValuesNewValueThrowsWhenAddFails(): void
    {
        FeatureValue::$mockAddResult = false;
        $this->mapper->expects(self::never())->method('save');

        $prestaValue = new FeatureValue();
        $jtlValue    = new JtlSpecificValue();
        $jtlValue->setId(new Identity('', 0));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error uploading specific value with id: ');

        $this->controller->exposeCreatePrestaSpecificValues($prestaValue, $jtlValue, '3');
    }
}
