<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\ManufacturerController;

use Db;
use Jtl\Connector\Core\Definition\IdentityType;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\Manufacturer as JtlManufacturer;
use Jtl\Connector\Core\Model\ManufacturerI18n as JtlManufacturerI18n;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use ObjectModel;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\Support\AfterHook;
use Tests\Support\Controller\TestableManufacturerController;

final class PushTest extends TestCase
{
    private TestableManufacturerController $controller;

    protected function setUp(): void
    {
        Db::resetInstance();
        ObjectModel::resetObjectModelMock();
        \Manufacturer::resetMock();
        // as PrestaShop's INSERT does
        ObjectModel::$mockNextInsertId = 12;
        $this->controller              = new TestableManufacturerController();
    }

    protected function tearDown(): void
    {
        Db::resetInstance();
        ObjectModel::resetObjectModelMock();
        \Manufacturer::resetMock();
    }

    private function buildJtlManufacturerWithI18n(string $endpoint, string $name): JtlManufacturer
    {
        return (new JtlManufacturer())
            ->setId(new Identity($endpoint, 3001))
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
        [$result] = $this->controller->push($jtlManufacturer);

        self::assertSame($jtlManufacturer, $result);
    }

    public function testPushNewManufacturerCallsMapperSaveAndReturnsModel(): void
    {
        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->expects(self::once())->method('save');
        $this->controller->injectMapper($mapper);

        $jtlManufacturer = $this->buildJtlManufacturerWithI18n('', 'New Brand');

        [$result] = $this->controller->push($jtlManufacturer);

        self::assertSame($jtlManufacturer, $result);
    }

    public function testNewManufacturerIsLinkedWithItsPrestaShopIdAsEndpoint(): void
    {
        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->expects(self::once())->method('save')->with(IdentityType::MANUFACTURER, '12', 3001);
        $this->controller->injectMapper($mapper);

        [$result] = $this->controller->push($this->buildJtlManufacturerWithI18n('', 'New Brand'));

        self::assertSame('12', $result->getId()->getEndpoint());
        self::assertSame(3001, $result->getId()->getHost());
    }

    public function testNewManufacturerIsLinkedWhenPsEventbusReadsTheStackInItsHook(): void
    {
        ObjectModel::$mockAfterHook = AfterHook::psEventbusErrorHandler();

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->expects(self::once())->method('save')->with(IdentityType::MANUFACTURER, '12', 3001);
        $this->controller->injectMapper($mapper);

        [$result] = $this->controller->push($this->buildJtlManufacturerWithI18n('', 'New Brand'));

        self::assertSame('12', $result->getId()->getEndpoint());
    }

    public function testNewManufacturerIsLinkedAndAWarningLoggedWhenAHookThrowsAfterTheInsert(): void
    {
        ObjectModel::$mockAfterHook = AfterHook::throwing(new \TypeError('ps_eventbus failed'));

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->expects(self::once())->method('save')->with(IdentityType::MANUFACTURER, '12', 3001);
        $this->controller->injectMapper($mapper);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(self::stringContains('Manufacturer 12'), self::anything());
        $this->controller->injectLogger($logger);

        [$result] = $this->controller->push($this->buildJtlManufacturerWithI18n('', 'New Brand'));

        self::assertSame('12', $result->getId()->getEndpoint());
    }

    public function testNewManufacturerIsNotLinkedWhenAddFails(): void
    {
        \Manufacturer::$mockAddResult = false;

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->expects(self::never())->method('save');
        $this->controller->injectMapper($mapper);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error uploading manufacturer');

        $this->controller->push($this->buildJtlManufacturerWithI18n('', 'New Brand'));
    }

    public function testUpdatesAndDeletesWithoutStackFramesThatPsEventbusCannotRead(): void
    {
        ObjectModel::$mockAfterHook = AfterHook::psEventbusErrorHandler();
        $jtlManufacturer            = $this->buildJtlManufacturerWithI18n('5', 'Existing Brand');

        self::assertSame([$jtlManufacturer], $this->controller->push($jtlManufacturer));
        self::assertSame([$jtlManufacturer], $this->controller->delete($jtlManufacturer));
    }
}
