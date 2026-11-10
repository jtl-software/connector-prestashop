<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\ImageController;

use PHPUnit\Framework\TestCase;

/**
 * The product image directory pull and statistic check (CO-3734). The test bootstrap defines PrestaShop 8/9's
 * _PS_PRODUCT_IMG_DIR_ in every process, so the PrestaShop 1.7 case runs in a PHP process of its own.
 */
final class ProductImageDirTest extends TestCase
{
    private function productImageDir(string $defines): string
    {
        $script = $defines . '
            require $argv[1] . "/lib/autoload.php";
            $class      = new ReflectionClass(\jtl\Connector\Presta\Controller\ImageController::class);
            $controller = $class->newInstanceWithoutConstructor();
            echo $class->getMethod("getProductImageDir")->invoke($controller);';

        \exec(
            \escapeshellarg(\PHP_BINARY) . ' -r ' . \escapeshellarg($script) . ' '
            . \escapeshellarg(\dirname(__DIR__, 4)) . ' 2>&1',
            $output,
            $exitCode
        );
        self::assertSame(0, $exitCode, \implode("\n", $output));

        return \implode("\n", $output);
    }

    public function testUsesTheProductImageDirOfPrestaShop8And9(): void
    {
        self::assertSame(
            '/shop/img/products/',
            $this->productImageDir(
                'define("_PS_IMG_DIR_", "/shop/img/"); define("_PS_PRODUCT_IMG_DIR_", "/shop/img/products/");'
            )
        );
    }

    public function testFallsBackToTheImgDirOnPrestaShop17(): void
    {
        // PrestaShop 1.7 (up to 1.7.8.11) defines the product image dir only under its legacy name, as _PS_IMG_DIR_.'p/'
        self::assertSame('/shop/img/p/', $this->productImageDir('define("_PS_IMG_DIR_", "/shop/img/");'));
    }
}
