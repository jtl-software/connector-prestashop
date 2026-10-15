<?php

declare(strict_types=1);

/**
 * Minimal test-only stand-ins for the Symfony classes composer "replace"s
 * (see composer.json) — at runtime these are provided by the PrestaShop 9
 * host, not vendored by this module, so they don't exist in lib/ and the
 * unit test suite needs its own stubs, same as PrestaShopStubs.php does for
 * PrestaShop core classes.
 */

namespace Symfony\Component\Yaml {
    if (!class_exists(Yaml::class)) {
        class Yaml
        {
            /**
             * Minimal "key: value" line parser, sufficient for this
             * project's build-config.yaml. Not a general YAML parser.
             *
             * @return array<string, string>
             */
            public static function parseFile(string $filename): array
            {
                $result = [];
                foreach (\file($filename, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                    [$key, $value] = \array_pad(\explode(':', $line, 2), 2, '');
                    $result[\trim($key)] = \trim($value);
                }
                return $result;
            }
        }
    }
}

namespace Symfony\Component\EventDispatcher {
    if (!class_exists(EventDispatcher::class)) {
        class EventDispatcher
        {
        }
    }
}
