<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\AbstractController;

use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\ConcreteAbstractController;

final class CreateDateTimeTest extends TestCase
{
    private ConcreteAbstractController $controller;

    protected function setUp(): void
    {
        $this->controller = new ConcreteAbstractController();
    }

    public function testCreateDateTimeReturnsNullForNullInput(): void
    {
        self::assertNull($this->controller->callCreateDateTime(null));
    }

    public function testCreateDateTimeReturnsNullForZeroDate(): void
    {
        self::assertNull($this->controller->callCreateDateTime('0000-00-00'));
    }

    public function testCreateDateTimeReturnsNullForDateBefore1970(): void
    {
        // 1969-12-31 is before the Unix epoch boundary of 1970-01-01 00:00:00
        self::assertNull($this->controller->callCreateDateTime('1969-12-31'));
    }

    public function testCreateDateTimeReturnsNullForDateTimeStillBefore1970(): void
    {
        self::assertNull($this->controller->callCreateDateTime('1969-12-31 23:59:59'));
    }

    public function testCreateDateTimeReturnsDateTimeInterfaceForEpochBoundary(): void
    {
        $result = $this->controller->callCreateDateTime('1970-01-01');

        self::assertInstanceOf(DateTimeInterface::class, $result);
        self::assertSame('1970-01-01', $result->format('Y-m-d'));
    }

    public function testCreateDateTimeReturnsDateTimeInterfaceForModernDate(): void
    {
        $result = $this->controller->callCreateDateTime('2023-06-15');

        self::assertInstanceOf(DateTimeInterface::class, $result);
        self::assertSame('2023-06-15', $result->format('Y-m-d'));
    }

    public function testCreateDateTimePreservesTimeComponent(): void
    {
        $result = $this->controller->callCreateDateTime('2023-06-15 10:30:00');

        self::assertInstanceOf(DateTimeInterface::class, $result);
        self::assertSame('2023-06-15 10:30:00', $result->format('Y-m-d H:i:s'));
    }

    public function testCreateDateTimeReturnsDateTimeForEpochItself(): void
    {
        // Exactly at epoch: must NOT return null
        $result = $this->controller->callCreateDateTime('1970-01-01 00:00:00');

        self::assertInstanceOf(DateTimeInterface::class, $result);
    }

    public function testCreateDateTimeReturnsNullForSecondBeforeEpoch(): void
    {
        // One second before epoch
        self::assertNull($this->controller->callCreateDateTime('1969-12-31 23:59:59'));
    }
}
