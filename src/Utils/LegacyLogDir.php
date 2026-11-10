<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Utils;

/**
 * Moves the log files of the legacy logs/ dir into var/log and removes logs/, which older releases shipped without
 * web access protection (the release ZIP no longer ships it). Used by JTLConnector::install() and
 * upgrade/install-3.0.0.php, which require this file directly instead of loading the bundled libraries: it must not
 * depend on them, and it must not need a newer PHP than jtlconnector.php itself (PHP 7.1 syntax), since install()
 * also runs on PHP versions it refuses.
 */
final class LegacyLogDir
{
    /** The legacy dir's own protection files are dropped: var/log is covered by var/.htaccess. */
    private const PROTECTION_FILES = ['.htaccess', 'index.php'];

    /** Written into a logs/ dir that cannot be removed, if it lacks them. */
    private const HTACCESS  = "Deny from all\n";
    private const INDEX_PHP = "<?php\n\nheader('Location: ../');\nexit;\n";

    /**
     * Never throws: PrestaShop's debug mode turns filesystem warnings (e.g. files the web server may not write) into
     * exceptions, which must not abort the module install or upgrade. A logs/ dir that cannot be removed keeps or gets
     * a "Deny from all" .htaccess and an index.php.
     *
     * @param string $connectorDir the module dir, with a trailing slash
     * @return bool false if logs/ could not be removed completely (e.g. a file could not be moved)
     */
    public static function migrate(string $connectorDir): bool
    {
        try {
            $moved = self::move($connectorDir);
        } catch (\Throwable $e) {
            $moved = false;
        }
        if (!$moved) {
            self::protect($connectorDir . 'logs');
        }

        return $moved;
    }

    /**
     * @param string $legacyDir
     * @return void
     */
    private static function protect(string $legacyDir): void
    {
        try {
            if (!\is_dir($legacyDir)) {
                return;
            }
            // @: best effort, a warning would only be turned into an exception by PrestaShop's debug mode
            if (!\is_file($legacyDir . '/.htaccess')) {
                @\file_put_contents($legacyDir . '/.htaccess', self::HTACCESS);
            }
            if (!\is_file($legacyDir . '/index.php')) {
                @\file_put_contents($legacyDir . '/index.php', self::INDEX_PHP);
            }
        } catch (\Throwable $e) {
            // best effort, see migrate()
        }
    }

    /**
     * @param string $connectorDir
     * @return bool
     */
    private static function move(string $connectorDir): bool
    {
        $legacyDir = $connectorDir . 'logs';
        if (!\is_dir($legacyDir)) {
            return true;
        }

        $logDir = $connectorDir . 'var/log';
        if (!\is_dir($logDir) && !\mkdir($logDir, 0777, true) && !\is_dir($logDir)) {
            return false;
        }

        $entries = self::entries($legacyDir);
        if ($entries === null) {
            // not listable (e.g. not readable): leave logs/ and its protection files alone
            return false;
        }
        foreach ($entries as $entry) {
            $file = $legacyDir . '/' . $entry;
            // sub dirs are not expected and are left in place; the protection files go last, see below
            if (
                \is_file($file)
                && !\in_array($entry, self::PROTECTION_FILES, true)
                && \copy($file, $logDir . '/' . $entry)
            ) {
                \unlink($file);
            }
        }

        // Only drop the protection files once nothing else is left: if a file could not be moved, logs/ keeps them.
        $remaining = self::entries($legacyDir);
        if ($remaining === null || \array_diff($remaining, self::PROTECTION_FILES) !== []) {
            return false;
        }
        foreach (self::PROTECTION_FILES as $entry) {
            if (\is_file($legacyDir . '/' . $entry)) {
                \unlink($legacyDir . '/' . $entry);
            }
        }

        return \rmdir($legacyDir);
    }

    /**
     * @param string $dir
     * @return list<string>|null the dir's entries without "." and "..", null if the dir cannot be listed
     */
    private static function entries(string $dir): ?array
    {
        // @: a failure is reported as null, a warning would only be turned into an exception by PrestaShop's debug mode
        $entries = @\scandir($dir);

        return $entries === false ? null : \array_values(\array_diff($entries, ['.', '..']));
    }
}
