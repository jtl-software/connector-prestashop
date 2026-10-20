<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\AbstractController;

use Context;
use Db;
use Language;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shop;
use Tests\Support\Controller\AbstractControllerWithRealHelpers;

final class ContextTest extends TestCase
{
    private Db $db;

    protected function setUp(): void
    {
        Db::resetInstance();
        Context::resetContext();
        $this->db = $this->createMock(Db::class);
    }

    protected function tearDown(): void
    {
        Context::resetContext();
    }

    private function makeController(): AbstractControllerWithRealHelpers
    {
        return new AbstractControllerWithRealHelpers($this->db);
    }

    public function testGetPrestaContextLanguageIdReturnsLanguageId(): void
    {
        $language     = new Language();
        $language->id = 42;

        $ctx           = new Context();
        $ctx->language = $language;
        Context::setContext($ctx);

        $result = $this->makeController()->exposeGetContextLanguageId();

        self::assertSame(42, $result);
    }

    public function testGetPrestaContextLanguageIdThrowsWhenLanguageIsNull(): void
    {
        $ctx           = new Context();
        $ctx->language = null;
        Context::setContext($ctx);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Language not found');

        $this->makeController()->exposeGetContextLanguageId();
    }

    public function testGetPrestaContextLanguageIdThrowsWhenContextIsNull(): void
    {
        // Context is already reset in setUp; do not set any context
        $this->expectException(RuntimeException::class);

        $this->makeController()->exposeGetContextLanguageId();
    }

    public function testGetPrestaContextShopIdReturnsShopId(): void
    {
        $shop     = new Shop(1);
        $shop->id = 1;

        $ctx       = new Context();
        $ctx->shop = $shop;
        Context::setContext($ctx);

        $result = $this->makeController()->exposeGetContextShopId();

        self::assertSame(1, $result);
    }

    public function testGetPrestaContextShopIdThrowsWhenShopIsNull(): void
    {
        $ctx       = new Context();
        $ctx->shop = null;
        Context::setContext($ctx);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Shop not found');

        $this->makeController()->exposeGetContextShopId();
    }

    public function testGetPrestaContextShopIdThrowsWhenContextIsNull(): void
    {
        // No context set
        $this->expectException(RuntimeException::class);

        $this->makeController()->exposeGetContextShopId();
    }
}
