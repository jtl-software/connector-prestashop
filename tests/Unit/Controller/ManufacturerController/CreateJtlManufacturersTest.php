<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\ManufacturerController;

use Db;
use Jtl\Connector\Core\Model\ManufacturerI18n as JtlManufacturerI18n;
use Manufacturer;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableManufacturerController;

final class CreateJtlManufacturersTest extends TestCase
{
    private TestableManufacturerController $controller;

    protected function setUp(): void
    {
        Db::resetInstance();
        $this->controller = new TestableManufacturerController();
    }

    protected function tearDown(): void
    {
        Db::resetInstance();
    }

    public function testCreateJtlManufacturersSetsIdFromPrestaManufacturerId(): void
    {
        $presta       = new Manufacturer(42);
        $presta->name = 'Nike';

        $this->controller->stubI18ns();

        $result = $this->controller->exposeCreateJtlManufacturers($presta);

        self::assertSame('42', $result->getId()->getEndpoint());
    }

    public function testCreateJtlManufacturersSetsNameFromPrestaManufacturerName(): void
    {
        $presta       = new Manufacturer(7);
        $presta->name = 'Adidas';

        $this->controller->stubI18ns();

        $result = $this->controller->exposeCreateJtlManufacturers($presta);

        self::assertSame('Adidas', $result->getName());
    }

    public function testCreateJtlManufacturersSetsI18nsFromStub(): void
    {
        $presta       = new Manufacturer(1);
        $presta->name = 'Brand';

        $stubbedI18n = (new JtlManufacturerI18n())
            ->setLanguageIso('eng')
            ->setDescription('Some desc');

        $this->controller->stubI18ns($stubbedI18n);

        $result = $this->controller->exposeCreateJtlManufacturers($presta);

        self::assertCount(1, $result->getI18ns());
        self::assertSame('eng', $result->getI18ns()[0]->getLanguageIso());
    }

    public function testCreateJtlManufacturersWithNullIdStillSetsStringId(): void
    {
        // Manufacturer with id=null → (string)null = ''
        $presta       = new Manufacturer();
        $presta->name = 'NoId Brand';

        $this->controller->stubI18ns();

        $result = $this->controller->exposeCreateJtlManufacturers($presta);

        // (string)null = ''
        self::assertSame('', $result->getId()->getEndpoint());
    }
}
