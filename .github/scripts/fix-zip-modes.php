<?php

declare(strict_types=1);

/*
 * Sets the Unix mode of every entry of the release ZIP to the mode of that directory or file in dist/. phing 2's
 * <zip> stores every directory entry with mode 0777, and file modes depend on libzip taking them from the source
 * file, so the ZIP unpacked with unzip on a server could leave the code (src/, lib/, ...) world-writable. In dist/,
 * only the runtime dirs and db/connector.s3db the build target chmods are 0777.
 *
 * Usage: php fix-zip-modes.php <release-zip> <dist-dir>
 */

$zipFile = $argv[1] ?? '';
$distDir = $argv[2] ?? '';
$fail    = static function (string $message): never {
    \fwrite(\STDERR, "fix-zip-modes: $message\n");
    exit(1);
};

$zip = new ZipArchive();
if ($zipFile === '' || $distDir === '' || $zip->open($zipFile) !== true) {
    $fail('usage: php fix-zip-modes.php <release-zip> <dist-dir>');
}

$fixed = 0;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = (string)$zip->getNameIndex($i);
    $path = $distDir . '/' . $name;
    $mode = \str_ends_with($name, '/')
        ? (\is_dir($path) ? \fileperms($path) : false)
        : (\is_file($path) ? \fileperms($path) : false);
    if ($mode === false) {
        $fail("$name is not in $distDir");
    }
    if (!$zip->setExternalAttributesIndex($i, ZipArchive::OPSYS_UNIX, ($mode & 0xFFFF) << 16)) {
        $fail("cannot set the mode of $name");
    }
    $fixed++;
}
if (!$zip->close()) {
    $fail("cannot write $zipFile");
}
echo "fix-zip-modes: set the mode of $fixed entries\n";
