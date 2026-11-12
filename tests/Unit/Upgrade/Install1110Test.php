<?php

declare(strict_types=1);

namespace Tests\Unit\Upgrade;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * The upgrade script needs _PS_VERSION_ and declares a global function, so each test gets its own process.
 */
#[RunTestsInSeparateProcesses]
final class Install1110Test extends TestCase
{
    private string $moduleDir;

    protected function setUp(): void
    {
        $this->moduleDir = \sys_get_temp_dir() . '/jtl_ps_upgrade_' . \bin2hex(\random_bytes(4)) . '/';
        \mkdir($this->moduleDir . 'config', 0777, true);
        \define('_PS_VERSION_', '9.0.3');
        require_once __DIR__ . '/../../../upgrade/install-1.11.0.php';
    }

    protected function tearDown(): void
    {
        @\unlink($this->moduleDir . 'config/features.json');
        \rmdir($this->moduleDir . 'config');
        \rmdir($this->moduleDir);
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

    public function testEnablesCustomerPushAndKeepsTheOtherFeatures(): void
    {
        $features = [
            'entities' => [
                'Customer' => ['pull' => true, 'push' => false, 'delete' => true],
                'Product'  => ['pull' => true, 'push' => false, 'delete' => false],
            ],
            'flags'    => ['foo' => true],
        ];
        \file_put_contents($this->moduleDir . 'config/features.json', \json_encode($features));

        self::assertTrue(\upgrade_module_1_11_0($this->module()));

        $features['entities']['Customer']['push'] = true;
        self::assertSame($features, \json_decode((string)\file_get_contents($this->moduleDir . 'config/features.json'), true));
    }

    public function testWithoutFeaturesFileNothingIsWritten(): void
    {
        self::assertTrue(\upgrade_module_1_11_0($this->module()));
        self::assertFileDoesNotExist($this->moduleDir . 'config/features.json');
    }
}
