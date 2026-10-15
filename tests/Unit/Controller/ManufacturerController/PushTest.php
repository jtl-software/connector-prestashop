<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\ManufacturerController;

use Db;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\Manufacturer as JtlManufacturer;
use Jtl\Connector\Core\Model\ManufacturerI18n as JtlManufacturerI18n;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableManufacturerController;

final class PushTest extends TestCase
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

    private function buildJtlManufacturerWithI18n(string $endpoint, string $name): JtlManufacturer
    {
        return (new JtlManufacturer())
            ->setId(new Identity($endpoint))
            ->setName($name)
            ->setI18ns(
                (new JtlManufacturerI18n())
                    ->setLanguageIso('eng')
                    ->setDescription('desc')
                    ->setTitleTag('title')
                    ->setMetaKeywords('kw')
                    ->setMetaDescription('meta')
            );
    }

    public function testPushExistingManufacturerReturnsModel(): void
    {
        $jtlManufacturer = $this->buildJtlManufacturerWithI18n('5', 'Existing Brand');

        // ObjectModel::update() returns true in the stub
        $result = $this->controller->push($jtlManufacturer);

        self::assertSame($jtlManufacturer, $result);
    }

    public function testPushNewManufacturerCallsMapperSaveAndReturnsModel(): void
    {
        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->expects(self::once())->method('save');
        $this->controller->injectMapper($mapper);

        $jtlManufacturer = $this->buildJtlManufacturerWithI18n('', 'New Brand');

        $result = $this->controller->push($jtlManufacturer);

        self::assertSame($jtlManufacturer, $result);
    }
}
