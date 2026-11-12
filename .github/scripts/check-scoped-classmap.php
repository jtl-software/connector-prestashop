<?php

declare(strict_types=1);

/*
 * Guards the PHP-Scoper directory exclusions in scoper.inc.php. It fails the build if any runtime class of a shipped
 * (non build-only) dependency was dropped from the scoped autoloader because a finder exclusion
 * (bin/doc/docs/test/tests/Tests/phing) was too broad. PHP-Scoper prefixes classes but never removes them, so every
 * class of the unscoped production build must still be present in the scoped one (prefixed, or kept under its original
 * name for the excluded namespaces and the global namespace).
 *
 * It also fails if a class that should be prefixed is instead present only under its original name: that means a vendor
 * namespace was wrongly excluded from scoping and its classes would collide with the copy PrestaShop loads first.
 *
 * Usage: php check-scoped-classmap.php <unscoped-authoritative-classmap.php> <scoped-classmap.php>
 */

$fail = static function (string $message): never {
    \fwrite(\STDERR, $message . "\n");
    exit(1);
};

$unscopedFile = $argv[1] ?? '';
$scopedFile   = $argv[2] ?? '';
if (!\is_file($unscopedFile) || !\is_file($scopedFile)) {
    $fail('check-scoped-classmap: classmap file(s) not found');
}

/** @var array<string, string> $unscoped */
$unscoped = require $unscopedFile;
/** @var array<string, string> $scoped */
$scoped = require $scopedFile;

// phing/phing is build-only and intentionally excluded from the release ZIP (see scoper.inc.php $buildOnlyPackages).
$buildOnlyPathFragment = '/phing/';
$prefix                = 'JtlConnectorPrestaVendor\\';

// Namespaces PHP-Scoper keeps unprefixed (scoper.inc.php $excludedNamespaces); every other shipped class must be
// present under the prefixed name. Matched the same way the scoper does: case-insensitively, on the namespace prefix.
$keptNamespaces = ['PrestaShop', 'PrestaShopBundle', 'Jtl\\Connector', 'Composer', 'Symfony\\Polyfill'];
$isKept         = static function (string $class) use ($keptNamespaces): bool {
    if (!\str_contains($class, '\\')) {
        return true; // the global namespace keeps its names
    }
    foreach ($keptNamespaces as $namespace) {
        if (\stripos($class . '\\', $namespace . '\\') === 0) {
            return true;
        }
    }

    return false;
};

$dropped    = []; // a kept class missing from the scoped autoloader (dropped by an over-broad finder exclusion)
$unprefixed = []; // a class that should be prefixed but is not present prefixed (dropped, or wrongly left unscoped)
foreach ($unscoped as $class => $path) {
    if (\str_contains((string)$path, $buildOnlyPathFragment)) {
        continue;
    }
    if ($isKept($class)) {
        if (!isset($scoped[$class])) {
            $dropped[] = $class;
        }
        continue;
    }
    if (!isset($scoped[$prefix . $class])) {
        $unprefixed[] = $class;
    }
}

if ($dropped !== [] || $unprefixed !== []) {
    $message = 'check-scoped-classmap: the scoped autoloader does not match the shipped classes';
    if ($dropped !== []) {
        \sort($dropped);
        $message .= \sprintf(
            "\n%d kept class(es) are missing (a scoper.inc.php finder exclusion is too broad):\n  %s",
            \count($dropped),
            \implode("\n  ", \array_slice($dropped, 0, 20))
        );
    }
    if ($unprefixed !== []) {
        \sort($unprefixed);
        $message .= \sprintf(
            "\n%d class(es) are not present prefixed (dropped, or a vendor namespace wrongly left unscoped so it "
            . "would collide with PrestaShop's own copy):\n  %s",
            \count($unprefixed),
            \implode("\n  ", \array_slice($unprefixed, 0, 20))
        );
    }
    $fail($message);
}

echo \sprintf("check-scoped-classmap: OK, all %d shipped classes present in the scoped autoloader\n", \count($scoped));
