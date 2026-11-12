<?php

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

/*
 * PHP-Scoper configuration for the release build (see build.xml, target "scope").
 *
 * PrestaShop registers its own Composer autoloader before any module code runs and ships its own copies of
 * Symfony, Monolog, psr/log, Doctrine, ... (PS 1.7: Symfony 3.4 / Monolog 1, PS 8: Symfony 4.4 / Monolog 1,
 * PS 9: Symfony 6.4 / Monolog 2). Those copies always win over the ones bundled in lib/, but jtl/connector 6
 * requires Symfony 7.4, Monolog 3, Doctrine DBAL 4, ... . Prefixing the bundled vendor code moves it into its
 * own namespace so the module always runs against the library versions it was built with.
 */

// also hard-coded in build.xml (target "scope") for the Composer autoload "files" identifiers
$prefix = 'JtlConnectorPrestaVendor';

// Required to run the build (lib/bin/phing), but not shipped: their directories are excluded from the finder below.
$buildOnlyPackages = ['phing/phing'];

$excludedNamespaces = [
    // PrestaShop's legacy API (Product, Db, Context, Module, pSQL(), _PS_VERSION_, ...) lives in the global
    // namespace, as do PHP's own symbols and the polyfilled functions.
    '/^$/',
    'PrestaShop',
    'PrestaShopBundle',
    // jtl/connector core, its bundled Dbc/MappingTables and this module: the JMS serializer metadata references
    // the model classes by name inside attribute strings, so these namespaces must keep their names.
    'Jtl\Connector',
    // Composer's ClassLoader/InstalledVersions are shared with PrestaShop's autoloader.
    'Composer',
    // The polyfills only back-fill PHP's own global functions and classes, and their global stubs (e.g. the
    // Normalizer class) extend the namespaced polyfill classes by their original name.
    'Symfony\Polyfill',
];

/*
 * Class names that are only built or compared as strings are invisible to PHP-Scoper, so they are prefixed by the
 * first patcher below. If the build fails because one of these files or strings is gone after a dependency update,
 * find where the class name is built now and update this list; the last patcher reports any string it still misses.
 */
$stringClassNames = [
    'lib/jtl/connector/src/Logger/LoggerService.php'                       => [
        "'Monolog\\Formatter\\%sFormatter'",
    ],
    'lib/php-di/php-di/src/Invoker/FactoryParameterResolver.php'           => [
        "'Psr\\Container\\ContainerInterface'",
        "'DI\\Factory\\RequestedEntry'",
    ],
    'lib/php-di/php-di/src/Compiler/ObjectCreationCompiler.php'            => [
        "'\\DI\\Definition\\Resolver\\ObjectCreator::",
    ],
    'lib/monolog/monolog/src/Monolog/Utils.php'                            => [
        "['Monolog\\Utils', ",
    ],
    'lib/monolog/monolog/src/Monolog/Handler/BrowserConsoleHandler.php'    => [
        "['Monolog\\Handler\\BrowserConsoleHandler', ",
    ],
    'lib/monolog/monolog/src/Monolog/Handler/PHPConsoleHandler.php'        => [
        "['Monolog\\\\']",
    ],
    'lib/monolog/monolog/src/Monolog/Processor/IntrospectionProcessor.php' => [
        "['Monolog\\\\']",
    ],
    'lib/fakerphp/faker/src/Faker/Factory.php'                             => [
        "'Faker\\\\' . ",
    ],
    'lib/fakerphp/faker/src/Faker/Documentor.php'                          => [
        "'Faker\\Provider\\Base'",
    ],
];
foreach (\array_keys($stringClassNames) as $file) {
    if (!\is_file(__DIR__ . '/' . $file)) {
        throw new \RuntimeException(\sprintf('Scoper patch for %s is outdated: the file does not exist', $file));
    }
}

/*
 * The namespaces that get prefixed: every namespace the installed packages autoload, minus the excluded ones.
 * Used by the last patcher to find class names in strings that the list above does not cover yet.
 */
$installed = \json_decode((string)\file_get_contents(__DIR__ . '/lib/composer/installed.json'), true);
$scopedNamespaces = [];
foreach ($installed['packages'] ?? [] as $package) {
    foreach (['psr-4', 'psr-0'] as $type) {
        foreach (\array_keys($package['autoload'][$type] ?? []) as $namespace) {
            $namespace = \trim((string)$namespace, '\\');
            foreach ($excludedNamespaces as $excluded) {
                if ($namespace === '' || \stripos($namespace . '\\', $excluded . '\\') === 0) {
                    continue 2;
                }
            }
            $scopedNamespaces[] = $namespace . '\\';
        }
    }
}

return [
    'prefix' => $prefix,

    'finders' => [
        Finder::create()->files()->name('*.php')->in(['src', 'controllers', 'upgrade']),
        Finder::create()->append(['jtlconnector.php', 'composer.json']),
        // non-PHP files of the packages (licenses, data files, ...) are copied unchanged
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->exclude([
                // Composer's bin proxies and the $buildOnlyPackages
                'bin',
                'phing',
                // documentation and test suites
                'doc',
                'docs',
                'test',
                'tests',
                'Tests',
            ])
            ->in('lib'),
    ],

    // Copied unchanged: an inline-HTML template for PHP-DI's container compiler, which PHP-Scoper cannot parse.
    'exclude-files' => [
        'lib/php-di/php-di/src/Compiler/Template.php',
    ],

    'exclude-namespaces' => $excludedNamespaces,

    // The global namespace is excluded above, so its symbols keep their names and need no aliases.
    'expose-global-classes'   => false,
    'expose-global-functions' => false,
    'expose-global-constants' => false,

    'patchers' => [
        /*
         * Drops the $buildOnlyPackages from Composer's package metadata: installed.json is what build.xml dumps the
         * autoloader from (their classmap directories are not shipped), installed.php backs Composer\InstalledVersions.
         */
        static function (string $filePath, string $prefix, string $contents) use ($buildOnlyPackages): string {
            if (\str_ends_with($filePath, 'lib/composer/installed.json')) {
                $installed           = \json_decode($contents, false, 512, \JSON_THROW_ON_ERROR);
                $installed->packages = \array_values(\array_filter(
                    $installed->packages,
                    static fn(\stdClass $package): bool => !\in_array($package->name, $buildOnlyPackages, true)
                ));

                return (string)\json_encode($installed, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);
            }
            if (\str_ends_with($filePath, 'lib/composer/installed.php')) {
                foreach ($buildOnlyPackages as $package) {
                    // "'<package>' => array(...), " with balanced parentheses, as PHP-Scoper prints it on one line
                    $pattern  = '/' . \preg_quote(\var_export($package, true), '/') . ' => array(\((?:[^()]++|(?1))*\)), /';
                    $contents = (string)\preg_replace($pattern, '', $contents, -1, $count);
                    if ($count !== 1) {
                        throw new \RuntimeException(\sprintf('Cannot remove %s from %s', $package, $filePath));
                    }
                }
            }

            return $contents;
        },
        static function (string $filePath, string $prefix, string $contents) use ($stringClassNames): string {
            foreach ($stringClassNames as $file => $searches) {
                if (!\str_ends_with($filePath, $file)) {
                    continue;
                }
                foreach ($searches as $search) {
                    if (!\str_contains($contents, $search)) {
                        throw new \RuntimeException(\sprintf('Scoper patch for %s is outdated: %s not found', $file, $search));
                    }
                    // the prefix goes right after the opening quote and an optional leading "\"
                    $position = \strpos($search, "'") + 1;
                    if ($search[$position] === '\\') {
                        $position++;
                    }
                    $replace  = \substr($search, 0, $position) . $prefix . '\\' . \substr($search, $position);
                    $contents = \str_replace($search, $replace, $contents);
                }
            }

            return $contents;
        },
        /*
         * PHP-Scoper still appends class_alias('<prefix>\Foo', 'Foo') to every class declared in the excluded global
         * namespace (JTLConnector, the front controller, polyfill stubs, ...). The prefixed class never exists, so
         * the alias would only raise a warning each time the file is loaded.
         */
        static function (string $filePath, string $prefix, string $contents): string {
            return (string)\preg_replace(
                '/^\s*\\\\class_alias\(\'' . \preg_quote($prefix, '/') . '\\\\(\w+)\', \'\1\', \\\\false\);\R/m',
                '',
                $contents
            );
        },
        /*
         * Fails the build on any string that still starts with a namespace that has been prefixed. Composer's own
         * autoload files are skipped, build.xml regenerates them after scoping.
         */
        static function (string $filePath, string $prefix, string $contents) use ($scopedNamespaces): string {
            if (!\str_ends_with($filePath, '.php') || \str_contains($filePath, '/lib/composer/')) {
                return $contents;
            }
            foreach (\PhpToken::tokenize($contents) as $token) {
                if (!$token->is([\T_CONSTANT_ENCAPSED_STRING, \T_ENCAPSED_AND_WHITESPACE])) {
                    continue;
                }
                $value = \ltrim(\str_replace('\\\\', '\\', \trim($token->text, '\'"')), '\\');
                foreach ($scopedNamespaces as $namespace) {
                    if (\stripos($value, $namespace) === 0) {
                        throw new \RuntimeException(\sprintf(
                            'Unprefixed class name in a string in %s on line %d: %s. Add it to $stringClassNames.',
                            $filePath,
                            $token->line,
                            $token->text
                        ));
                    }
                }
            }

            return $contents;
        },
    ],
];
