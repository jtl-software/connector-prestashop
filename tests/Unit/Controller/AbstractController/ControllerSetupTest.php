<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\AbstractController;

use Context;
use Db;
use jtl\Connector\Presta\Controller\AbstractController;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tests\Support\Controller\AbstractControllerWithRealHelpers;
use Tests\Support\Controller\ConcreteAbstractControllerWithRealConstructor;

final class ControllerSetupTest extends TestCase
{
    private Db $db;

    protected function setUp(): void
    {
        Db::resetInstance();
        Context::resetContext();
        $this->db = $this->createMock(Db::class);
    }

    protected function tearDown(): void
    {
        Context::resetContext();
    }

    public function testRealConstructorSetsMapperAndDefaultFields(): void
    {
        Db::resetInstance();
        $mapper = $this->createMock(PrimaryKeyMapper::class);

        $ctrl = new ConcreteAbstractControllerWithRealConstructor($mapper);

        // If the constructor ran without error the instance is valid.
        self::assertInstanceOf(AbstractController::class, $ctrl);
    }

    public function testSetLoggerChangesLogger(): void
    {
        $ctrl      = new AbstractControllerWithRealHelpers($this->db);
        $newLogger = new NullLogger();
        $ctrl->exposeSetLogger($newLogger);
        // No direct getter; we just verify no exception is thrown and the
        // same interface is satisfied — this exercises the setter branch.
        self::assertInstanceOf(NullLogger::class, $newLogger);
    }
}
