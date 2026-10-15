<?php

declare(strict_types=1);

namespace Tests\Unit\Upgrade;

use PHPUnit\Framework\TestCase;

final class Install300Test extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!\defined('_PS_VERSION_')) {
            \define('_PS_VERSION_', '9.0.0');
        }

        require_once __DIR__ . '/../../../upgrade/install-3.0.0.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        \Tab::resetMock();
        \Language::resetMock();
        \Db::resetInstance();
    }

    protected function tearDown(): void
    {
        \Tab::resetMock();
        \Language::resetMock();
        \Db::resetInstance();

        parent::tearDown();
    }

    public function testAlreadyMigratedTabIsNotRecreatedOrDeleted(): void
    {
        \Tab::$classNames = ['AdminJtlconnector' => 12];
        $this->useDb([]);

        self::assertTrue(\upgrade_module_3_0_0($this->module()));
        self::assertSame([], \Tab::$saved);
        self::assertSame([], \Tab::$deletedIds);
    }

    public function testLeftoverLegacyTabIsDeletedWhenTheNewTabAlreadyExists(): void
    {
        \Tab::$classNames = ['AdminJtlconnector' => 12, 'jtlconnector' => 7];
        $this->useDb([]);

        self::assertTrue(\upgrade_module_3_0_0($this->module()));
        self::assertSame([], \Tab::$saved);
        self::assertSame([7], \Tab::$deletedIds);
    }

    public function testInterruptedEarlierRunStillGetsItsRolesRenamed(): void
    {
        \Tab::$classNames = ['AdminJtlconnector' => 12];
        $statements       = $this->useDb([]);

        self::assertTrue(\upgrade_module_3_0_0($this->module()));

        $updates = \array_values(\array_filter($statements(), static fn(string $sql): bool => \str_starts_with($sql, 'UPDATE')));
        self::assertCount(4, $updates);
        self::assertSame([12], \Tab::$initAccessCalls);
    }

    public function testFailsWhenRenamingTheRolesFails(): void
    {
        \Tab::$classNames = ['jtlconnector' => 7];
        $this->useDb([], false);

        self::assertFalse(\upgrade_module_3_0_0($this->module()));
        self::assertSame([], \Tab::$initAccessCalls);
    }

    public function testLegacyTabIsRenamedInPlaceKeepingItsId(): void
    {
        \Tab::$classNames = ['jtlconnector' => 7, 'IMPROVE' => 3];
        $this->useDb([]);

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
        $statements       = $this->useDb([]);

        self::assertTrue(\upgrade_module_3_0_0($this->module()));

        $updates = \array_values(\array_filter($statements(), static fn(string $sql): bool => \str_starts_with($sql, 'UPDATE')));
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

        $updates = \array_values(\array_filter($statements(), static fn(string $sql): bool => \str_starts_with($sql, 'UPDATE')));
        self::assertCount(3, $updates);
        foreach ($updates as $sql) {
            self::assertStringNotContainsString('ADMINJTLCONNECTOR_READ"', $sql);
        }
    }

    public function testRolesAreNotTouchedWhenSavingTheRenamedTabFails(): void
    {
        \Tab::$classNames = ['jtlconnector' => 7];
        \Tab::$saveResult = false;
        $statements       = $this->useDb([]);

        self::assertFalse(\upgrade_module_3_0_0($this->module()));
        self::assertSame([], $statements());
        self::assertSame([], \Tab::$initAccessCalls);
    }

    public function testMissingTabIsCreatedUnderImprove(): void
    {
        \Tab::$classNames         = ['IMPROVE' => 3];
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

    public function testFailsWhenParentTabIsMissing(): void
    {
        self::assertFalse(\upgrade_module_3_0_0($this->module()));
        self::assertSame([], \Tab::$saved);
    }

    public function testReturnsFalseWhenSavingFails(): void
    {
        \Tab::$classNames = ['jtlconnector' => 7];
        \Tab::$saveResult = false;

        self::assertFalse(\upgrade_module_3_0_0($this->module()));
    }

    private function module(): object
    {
        return (object)['name' => 'jtlconnector'];
    }

    /**
     * Installs a Db double that records every executed statement and reports the given
     * authorization_role slugs as existing.
     *
     * @param array<int, string> $existingSlugs
     * @param bool $executeResult result reported for every executed statement
     * @return callable(): array<int, string> returns the recorded statements
     */
    private function useDb(array $existingSlugs, bool $executeResult = true): callable
    {
        $log = new \ArrayObject();

        $db = new class ($log, $existingSlugs, $executeResult) extends \Db {
            /**
             * @param \ArrayObject<int, string> $log
             * @param array<int, string> $existing
             */
            public function __construct(
                private \ArrayObject $log,
                private array $existing,
                private bool $executeResult
            ) {
            }

            public function execute(mixed $sql, bool $useCache = true): bool
            {
                $this->log->append((string)$sql);

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
