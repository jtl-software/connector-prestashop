<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use jtl\Connector\Presta\Utils\LegacyLogDir;
use PHPUnit\Framework\TestCase;

/**
 * Stream wrapper for an install whose logs/ exists (with log and protection files) but cannot be listed, like a dir
 * the web server may write to but not read. Records every write, so tests can assert nothing was deleted or created.
 */
final class UnlistableLegacyDirStream
{
    public const ROOT = 'unlistable-legacy://module/';

    /** @var list<string> */
    public static array $changes = [];

    /** @var resource|null */
    public $context;

    /**
     * @return array<string, int>|false
     */
    public function url_stat(string $path, int $flags): array|false
    {
        $relative = \substr($path, \strlen(self::ROOT));
        if (\in_array($relative, ['logs', 'var', 'var/log'], true)) {
            return ['mode' => 0040755];
        }
        if (\in_array($relative, ['logs/old.log', 'logs/.htaccess', 'logs/index.php'], true)) {
            return ['mode' => 0100644, 'size' => 1];
        }

        return false;
    }

    public function dir_opendir(string $path, int $options): bool
    {
        return false;
    }

    public function unlink(string $path): bool
    {
        self::$changes[] = "unlink $path";
        return true;
    }

    public function rmdir(string $path, int $options): bool
    {
        self::$changes[] = "rmdir $path";
        return true;
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        self::$changes[] = "mkdir $path";
        return true;
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        self::$changes[] = "open $path ($mode)";
        return false;
    }
}

/**
 * LegacyLogDir::migrate() moves the unprotected legacy logs/ dir into var/log (install and upgrade 3.0.0).
 */
final class LegacyLogDirTest extends TestCase
{
    private string $moduleDir;

    protected function setUp(): void
    {
        $this->moduleDir = \sys_get_temp_dir() . '/jtlconnector-legacy-log-' . \bin2hex(\random_bytes(4)) . '/';
        \mkdir($this->moduleDir);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->moduleDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? \rmdir($file->getPathname()) : \unlink($file->getPathname());
        }
        \rmdir($this->moduleDir);
    }

    public function testWithoutLegacyDirDoesNothing(): void
    {
        self::assertTrue(LegacyLogDir::migrate($this->moduleDir));
        self::assertDirectoryDoesNotExist($this->moduleDir . 'var');
    }

    public function testMovesLogFilesDropsProtectionFilesAndRemovesLegacyDir(): void
    {
        \mkdir($this->moduleDir . 'logs');
        \file_put_contents($this->moduleDir . 'logs/old.log', 'old log');
        \file_put_contents($this->moduleDir . 'logs/.htaccess', 'Deny from all');
        \file_put_contents($this->moduleDir . 'logs/index.php', '<?php');

        self::assertTrue(LegacyLogDir::migrate($this->moduleDir));

        self::assertDirectoryDoesNotExist($this->moduleDir . 'logs');
        self::assertSame('old log', \file_get_contents($this->moduleDir . 'var/log/old.log'));
        self::assertFileDoesNotExist($this->moduleDir . 'var/log/.htaccess');
        self::assertFileDoesNotExist($this->moduleDir . 'var/log/index.php');
    }

    public function testKeepsExistingVarLogFiles(): void
    {
        \mkdir($this->moduleDir . 'var/log', 0777, true);
        \file_put_contents($this->moduleDir . 'var/log/current.log', 'current log');
        \mkdir($this->moduleDir . 'logs');
        \file_put_contents($this->moduleDir . 'logs/old.log', 'old log');

        self::assertTrue(LegacyLogDir::migrate($this->moduleDir));

        self::assertSame('current log', \file_get_contents($this->moduleDir . 'var/log/current.log'));
        self::assertSame('old log', \file_get_contents($this->moduleDir . 'var/log/old.log'));
    }

    public function testNeverThrowsWhenFilesystemWarningsBecomeExceptions(): void
    {
        \mkdir($this->moduleDir . 'logs');
        \file_put_contents($this->moduleDir . 'logs/old.log', 'old log');
        \file_put_contents($this->moduleDir . 'logs/.htaccess', 'Deny from all');
        // var is a file, so creating var/log raises a warning
        \file_put_contents($this->moduleDir . 'var', '');

        // like PrestaShop's debug mode: every warning is thrown
        \set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            $migrated = LegacyLogDir::migrate($this->moduleDir);
        } finally {
            \restore_error_handler();
        }

        self::assertFalse($migrated);
        self::assertFileExists($this->moduleDir . 'logs/old.log');
        self::assertFileExists($this->moduleDir . 'logs/.htaccess');
        // the missing index.php is added, the remaining logs/ stays protected
        self::assertFileExists($this->moduleDir . 'logs/index.php');
    }

    public function testKeepsAnUnlistableLegacyDirAndItsProtectionUntouched(): void
    {
        \stream_wrapper_register('unlistable-legacy', UnlistableLegacyDirStream::class);
        UnlistableLegacyDirStream::$changes = [];
        try {
            $migrated = LegacyLogDir::migrate(UnlistableLegacyDirStream::ROOT);
        } finally {
            \stream_wrapper_unregister('unlistable-legacy');
        }

        self::assertFalse($migrated);
        // nothing may be deleted when the dir's content is unknown, and its protection files are already there
        self::assertSame([], UnlistableLegacyDirStream::$changes);
    }

    public function testProtectsALegacyDirWithoutProtectionFilesThatCannotBeRemoved(): void
    {
        // older releases shipped logs/ without .htaccess and index.php
        \mkdir($this->moduleDir . 'logs/sub', 0777, true);
        \file_put_contents($this->moduleDir . 'logs/old.log', 'old log');

        self::assertFalse(LegacyLogDir::migrate($this->moduleDir));

        self::assertStringContainsString('Deny from all', (string)\file_get_contents($this->moduleDir . 'logs/.htaccess'));
        self::assertFileExists($this->moduleDir . 'logs/index.php');
    }

    public function testLeavesUnexpectedSubDirsAndKeepsTheProtectionOfTheRemainingLegacyDir(): void
    {
        \mkdir($this->moduleDir . 'logs/sub', 0777, true);
        \file_put_contents($this->moduleDir . 'logs/old.log', 'old log');
        \file_put_contents($this->moduleDir . 'logs/.htaccess', 'Deny from all');
        \file_put_contents($this->moduleDir . 'logs/index.php', '<?php');

        self::assertFalse(LegacyLogDir::migrate($this->moduleDir));

        self::assertDirectoryExists($this->moduleDir . 'logs/sub');
        self::assertFileDoesNotExist($this->moduleDir . 'logs/old.log');
        self::assertSame('old log', \file_get_contents($this->moduleDir . 'var/log/old.log'));
        // logs/ is still there, so it must stay protected
        self::assertFileExists($this->moduleDir . 'logs/.htaccess');
        self::assertFileExists($this->moduleDir . 'logs/index.php');
    }
}
