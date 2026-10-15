<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The module must not vendor symfony/* (they collide with the copies PrestaShop 9
 * ships), see the "replace" block in composer.json.
 */
final class ComposerReplaceTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    public function testLockFileContainsNoSymfonyPackage(): void
    {
        $lock     = $this->readJson('composer.lock');
        $packages = \array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);

        $symfony = \array_values(\array_filter(
            \array_column($packages, 'name'),
            static fn(string $name): bool => \str_starts_with($name, 'symfony/')
        ));

        self::assertSame([], $symfony, 'symfony/* must be replaced, not vendored');
    }

    public function testEverySymfonyPackageRequiredByTheCoreIsReplaced(): void
    {
        $replace  = $this->readJson('composer.json')['replace'] ?? [];
        $coreFile = self::ROOT . 'lib/jtl/connector/composer.json';
        if (!\is_file($coreFile)) {
            self::markTestSkipped('jtl/connector is not installed');
        }
        /** @var array{require?: array<string, string>} $core */
        $core = \json_decode((string)\file_get_contents($coreFile), true, 512, \JSON_THROW_ON_ERROR);

        foreach (\array_keys($core['require'] ?? []) as $package) {
            if (\str_starts_with($package, 'symfony/')) {
                self::assertArrayHasKey($package, $replace, "$package is required by jtl/connector but not replaced");
            }
        }
    }

    public function testNoDirectSymfonyRequirement(): void
    {
        $require = $this->readJson('composer.json')['require'] ?? [];

        foreach (\array_keys($require) as $package) {
            self::assertStringStartsNotWith('symfony/', $package, "$package must not be required directly");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $file): array
    {
        /** @var array<string, mixed> */
        return \json_decode((string)\file_get_contents(self::ROOT . $file), true, 512, \JSON_THROW_ON_ERROR);
    }
}
