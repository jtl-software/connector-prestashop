<?php

declare(strict_types=1);

namespace Tests\Unit\Upgrade;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * The 3.0.0 upgrade: removal of the broken manufacturer link that 2.x saved (CO-3735) and migration of the back office
 * menu entry to the class name of the admin controller PrestaShop 9 requires (CO-3113). The move of the legacy logs/
 * dir is covered by LegacyLogDirTest and the ZIP smoke test.
 *
 * The upgrade script needs _PS_VERSION_ and declares global functions, so each test gets its own process.
 */
#[RunTestsInSeparateProcesses]
final class Install300Test extends TestCase
{
    private string $moduleDir;

    protected function setUp(): void
    {
        // a module dir without logs/, so that the logs/ move has nothing to do
        $this->moduleDir = \sys_get_temp_dir() . '/jtl_ps_upgrade_' . \bin2hex(\random_bytes(4)) . '/';
        \mkdir($this->moduleDir . 'src/Utils', 0777, true);
        \copy(__DIR__ . '/../../../src/Utils/LegacyLogDir.php', $this->moduleDir . 'src/Utils/LegacyLogDir.php');
        \define('_PS_VERSION_', '9.2.0');
        require_once __DIR__ . '/../../../upgrade/install-3.0.0.php';
        \Tab::resetMock();
        \Language::resetMock();
    }

    protected function tearDown(): void
    {
        \Db::resetInstance();
        @\unlink($this->moduleDir . 'src/Utils/LegacyLogDir.php');
        @\rmdir($this->moduleDir . 'src/Utils');
        @\rmdir($this->moduleDir . 'src');
        @\rmdir($this->moduleDir);
    }

    // ---- manufacturer link (CO-3735)

    public function testRemovesTheManufacturerLinkWithoutEndpoint(): void
    {
        \Tab::$classNames = ['AdminJtlconnector' => 12];
        $statements       = $this->useDb();

        self::assertTrue(\upgrade_module_3_0_0($this->module()));
        self::assertContains('DELETE FROM `jtl_connector_link_manufacturer` WHERE `endpoint_id` = 0', $statements());
    }

    public function testADatabaseErrorOnTheManufacturerLinkDoesNotBlockTheUpgrade(): void
    {
        \Tab::$classNames = ['jtlconnector' => 7];
        $statements       = $this->useDb([], true, new \PrestaShopDatabaseException('table is missing'));

        self::assertTrue(\upgrade_module_3_0_0($this->module()));
        // the menu entry is migrated all the same
        self::assertCount(4, $this->roleRenames($statements()));
        self::assertSame([7], \Tab::$initAccessCalls);
    }

    // ---- back office menu entry (CO-3113)

    public function testLegacyTabIsRenamedInPlaceKeepingItsId(): void
    {
        \Tab::$classNames = ['jtlconnector' => 7, 'IMPROVE' => 3];
        $this->useDb();

        self::assertTrue(\upgrade_module_3_0_0($this->module()));
        self::assertCount(1, \Tab::$saved);
        self::assertSame(7, \Tab::$saved[0]->id);
        self::assertSame('AdminJtlconnector', \Tab::$saved[0]->class_name);
        self::assertFalse(\Tab::$saved[0]->deleted);
        self::assertSame([7], \Tab::$initAccessCalls);
    }

    public function testPermissionRolesOfTheLegacyTabAreRenamed(): void
    {
        \Tab::$classNames = ['jtlconnector' => 7];
        $statements       = $this->useDb();

        self::assertTrue(\upgrade_module_3_0_0($this->module()));

        $updates = $this->roleRenames($statements());
        self::assertCount(4, $updates);
        foreach (['CREATE', 'READ', 'UPDATE', 'DELETE'] as $i => $action) {
            self::assertStringContainsString('SET `slug` = "ROLE_MOD_TAB_ADMINJTLCONNECTOR_' . $action . '"', $updates[$i]);
            self::assertStringContainsString('WHERE `slug` = "ROLE_MOD_TAB_JTLCONNECTOR_' . $action . '"', $updates[$i]);
            self::assertStringContainsString('`ps_authorization_role`', $updates[$i]);
        }
    }

    public function testExistingNewPermissionRoleIsNotOverwritten(): void
    {
        \Tab::$classNames = ['jtlconnector' => 7];
        $statements       = $this->useDb(['ROLE_MOD_TAB_ADMINJTLCONNECTOR_READ']);

        self::assertTrue(\upgrade_module_3_0_0($this->module()));

        $updates = $this->roleRenames($statements());
        self::assertCount(3, $updates);
        foreach ($updates as $sql) {
            self::assertStringNotContainsString('ADMINJTLCONNECTOR_READ"', $sql);
        }
    }

    public function testRolesAreNotTouchedWhenSavingTheRenamedTabFails(): void
    {
        \Tab::$classNames = ['jtlconnector' => 7];
        \Tab::$saveResult = false;
        $statements       = $this->useDb();

        self::assertFalse(\upgrade_module_3_0_0($this->module()));
        self::assertSame([], $this->roleRenames($statements()));
        self::assertSame([], \Tab::$initAccessCalls);
    }

    public function testFailsWhenRenamingTheRolesFails(): void
    {
        \Tab::$classNames = ['jtlconnector' => 7];
        $this->useDb([], false);

        self::assertFalse(\upgrade_module_3_0_0($this->module()));
        self::assertSame([], \Tab::$initAccessCalls);
    }

    public function testAlreadyMigratedTabIsNotRecreatedOrDeleted(): void
    {
        \Tab::$classNames = ['AdminJtlconnector' => 12];
        $this->useDb();

        self::assertTrue(\upgrade_module_3_0_0($this->module()));
        self::assertSame([], \Tab::$saved);
        self::assertSame([], \Tab::$deletedIds);
    }

    public function testInterruptedEarlierRunStillGetsItsRolesRenamed(): void
    {
        \Tab::$classNames = ['AdminJtlconnector' => 12];
        $statements       = $this->useDb();

        self::assertTrue(\upgrade_module_3_0_0($this->module()));
        self::assertCount(4, $this->roleRenames($statements()));
        self::assertSame([12], \Tab::$initAccessCalls);
    }

    public function testLeftoverLegacyTabIsDeletedAfterItsRolesWereRenamed(): void
    {
        \Tab::$classNames = ['AdminJtlconnector' => 12, 'jtlconnector' => 7];
        $this->useDb();

        self::assertTrue(\upgrade_module_3_0_0($this->module()));
        self::assertSame([], \Tab::$saved);
        self::assertSame([7], \Tab::$deletedIds);
        // Tab::delete() removes the roles named after the deleted tab, so they have to be renamed first
        $updates = \array_keys(
            \array_filter(\Tab::$events, static fn(string $event): bool => \str_starts_with($event, 'UPDATE'))
        );
        $delete  = \array_search('delete:7', \Tab::$events, true);
        self::assertCount(4, $updates);
        self::assertNotFalse($delete);
        self::assertLessThan($delete, \max($updates));
    }

    public function testMissingTabIsAddedUnderImprove(): void
    {
        \Tab::$classNames             = ['IMPROVE' => 3];
        \Language::$mockLanguagesList = [['id_lang' => 1], ['id_lang' => '2']];

        self::assertTrue(\upgrade_module_3_0_0($this->module()));
        self::assertCount(1, \Tab::$saved);
        $tab = \Tab::$saved[0];
        self::assertSame(0, $tab->id);
        self::assertSame(3, $tab->id_parent);
        self::assertSame('AdminJtlconnector', $tab->class_name);
        self::assertSame('jtlconnector', $tab->module);
        self::assertTrue($tab->active);
        self::assertSame([1 => 'JTL-Connector', 2 => 'JTL-Connector'], $tab->name);
    }

    public function testFailsWhenTheParentTabIsMissing(): void
    {
        self::assertFalse(\upgrade_module_3_0_0($this->module()));
        self::assertSame([], \Tab::$saved);
    }

    private function module(): object
    {
        return new class ($this->moduleDir) {
            public string $name = 'jtlconnector';

            public function __construct(private readonly string $localPath)
            {
            }

            public function getLocalPath(): string
            {
                return $this->localPath;
            }
        };
    }

    /**
     * @param array<int, string> $statements
     * @return array<int, string> the authorization_role renames among them
     */
    private function roleRenames(array $statements): array
    {
        return \array_values(
            \array_filter($statements, static fn(string $sql): bool => \str_starts_with($sql, 'UPDATE'))
        );
    }

    /**
     * Installs a Db double that records every executed statement and reports the given authorization_role slugs as
     * existing.
     *
     * @param array<int, string> $existingSlugs
     * @param bool               $executeResult result of every executed statement
     * @param \Throwable|null    $deleteError   thrown by the manufacturer link DELETE
     * @return callable(): array<int, string> returns the recorded statements
     */
    private function useDb(
        array $existingSlugs = [],
        bool $executeResult = true,
        ?\Throwable $deleteError = null
    ): callable {
        $log = new \ArrayObject();
        $db  = new class ($log, $existingSlugs, $executeResult, $deleteError) extends \Db {
            /**
             * @param \ArrayObject<int, string> $log
             * @param array<int, string>        $existing
             */
            public function __construct(
                private \ArrayObject $log,
                private array $existing,
                private bool $executeResult,
                private ?\Throwable $deleteError
            ) {
            }

            public function execute(mixed $sql, bool $useCache = true): bool
            {
                $this->log->append((string)$sql);
                \Tab::$events[] = (string)$sql;
                if ($this->deleteError !== null && \str_starts_with((string)$sql, 'DELETE')) {
                    throw $this->deleteError;
                }

                return $this->executeResult;
            }

            public function getValue(mixed $sql, bool $useCache = true): mixed
            {
                foreach ($this->existing as $slug) {
                    if (\str_contains((string)$sql, '"' . $slug . '"')) {
                        return $slug;
                    }
                }

                return null;
            }
        };
        \Db::setInstance($db);

        return static fn(): array => $log->getArrayCopy();
    }
}
