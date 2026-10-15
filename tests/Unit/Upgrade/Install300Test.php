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
    }

    protected function tearDown(): void
    {
        \Tab::resetMock();
        \Language::resetMock();

        parent::tearDown();
    }

    public function testAlreadyMigratedTabIsLeftUntouched(): void
    {
        \Tab::$classNames = ['AdminJtlconnector' => 12, 'jtlconnector' => 7];

        self::assertTrue(\upgrade_module_3_0_0($this->module()));
        self::assertSame([], \Tab::$saved);
    }

    public function testLegacyTabIsRenamedInPlaceKeepingItsId(): void
    {
        \Tab::$classNames = ['jtlconnector' => 7, 'IMPROVE' => 3];

        self::assertTrue(\upgrade_module_3_0_0($this->module()));

        self::assertCount(1, \Tab::$saved);
        self::assertSame(7, \Tab::$saved[0]->id);
        self::assertSame('AdminJtlconnector', \Tab::$saved[0]->class_name);
        self::assertFalse(\Tab::$saved[0]->deleted);
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
}
