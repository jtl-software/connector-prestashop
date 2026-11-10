<?php

declare(strict_types=1);

namespace Tests\Unit\Upgrade;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * The 3.0.0 upgrade's removal of the broken manufacturer link that 2.x saved (CO-3735). The move of the legacy logs/
 * dir is covered by LegacyLogDirTest and the ZIP smoke test.
 *
 * The upgrade script needs _PS_VERSION_ and declares a global function, so each test gets its own process.
 */
#[RunTestsInSeparateProcesses]
final class Install300Test extends TestCase
{
    private string $moduleDir;

    protected function setUp(): void
    {
        // a module dir without logs/, so that the logs/ move has nothing to do
        $this->moduleDir = \sys_get_temp_dir() . '/jtl_ps_upgrade_' . \bin2hex(\random_bytes(4)) . '/';
        \define('_PS_VERSION_', '9.0.3');
        require_once __DIR__ . '/../../../upgrade/install-3.0.0.php';
    }

    protected function tearDown(): void
    {
        \Db::resetInstance();
        @\unlink($this->moduleDir . 'src/Utils/LegacyLogDir.php');
        @\rmdir($this->moduleDir . 'src/Utils');
        @\rmdir($this->moduleDir . 'src');
        @\rmdir($this->moduleDir);
    }

    private function module(): object
    {
        return new class ($this->moduleDir) {
            public function __construct(private readonly string $localPath)
            {
            }

            public function getLocalPath(): string
            {
                return $this->localPath;
            }
        };
    }

    public function testRemovesTheManufacturerLinkWithoutEndpoint(): void
    {
        $db = $this->createMock(\Db::class);
        $db->expects(self::once())
            ->method('execute')
            ->with('DELETE FROM `jtl_connector_link_manufacturer` WHERE `endpoint_id` = 0')
            ->willReturn(true);
        \Db::setInstance($db);
        $this->copyLegacyLogDir();

        self::assertTrue(\upgrade_module_3_0_0($this->module()));
    }

    public function testADatabaseErrorDoesNotBlockTheUpgrade(): void
    {
        $db = $this->createMock(\Db::class);
        $db->method('execute')->willThrowException(new \PrestaShopDatabaseException('table is missing'));
        \Db::setInstance($db);
        $this->copyLegacyLogDir();

        self::assertTrue(\upgrade_module_3_0_0($this->module()));
    }

    private function copyLegacyLogDir(): void
    {
        \mkdir($this->moduleDir . 'src/Utils', 0777, true);
        \copy(__DIR__ . '/../../../src/Utils/LegacyLogDir.php', $this->moduleDir . 'src/Utils/LegacyLogDir.php');
    }
}
