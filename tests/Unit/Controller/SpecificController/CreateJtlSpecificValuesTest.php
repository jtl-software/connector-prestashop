<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\SpecificController;

use Jtl\Connector\Core\Model\SpecificValue as JtlSpecificValue;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableSpecificController;

final class CreateJtlSpecificValuesTest extends TestCase
{
    private TestableSpecificController $controller;

    protected function setUp(): void
    {
        $this->controller = new TestableSpecificController();
    }

    public function testCreateJtlSpecificValuesEmptyInputReturnsEmptyArray(): void
    {
        $result = $this->controller->exposeCreateJtlSpecificValues([]);

        self::assertSame([], $result);
    }

    public function testCreateJtlSpecificValuesSingleEntry(): void
    {
        $this->controller->setMockSpecificValueI18ns([]);
        $data   = [['id_feature_value' => '7']];
        $result = $this->controller->exposeCreateJtlSpecificValues($data);

        self::assertCount(1, $result);
        self::assertInstanceOf(JtlSpecificValue::class, $result[0]);
    }

    public function testCreateJtlSpecificValuesMultipleEntries(): void
    {
        $this->controller->setMockSpecificValueI18ns([]);
        $data   = [
            ['id_feature_value' => '1'],
            ['id_feature_value' => '2'],
            ['id_feature_value' => '3'],
        ];
        $result = $this->controller->exposeCreateJtlSpecificValues($data);

        self::assertCount(3, $result);
        foreach ($result as $item) {
            self::assertInstanceOf(JtlSpecificValue::class, $item);
        }
    }

    public function testCreateJtlSpecificValuesSetsEmptyIdentityByDefault(): void
    {
        $this->controller->setMockSpecificValueI18ns([]);
        $data   = [['id_feature_value' => '5']];
        $result = $this->controller->exposeCreateJtlSpecificValues($data);

        // The Id is set via new Identity() (no args) → endpoint is ''
        self::assertSame('', $result[0]->getId()->getEndpoint());
    }

    public function testCreateJtlSpecificValuesI18nsBubbledIn(): void
    {
        $this->controller->setMockSpecificValueI18ns([
            ['id_lang' => '1', 'value' => 'Silber'],
        ]);
        $data   = [['id_feature_value' => '10']];
        $result = $this->controller->exposeCreateJtlSpecificValues($data);

        self::assertCount(1, $result[0]->getI18ns());
        self::assertSame('Silber', $result[0]->getI18ns()[0]->getValue());
    }
}
