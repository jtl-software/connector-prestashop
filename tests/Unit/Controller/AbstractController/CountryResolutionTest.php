<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\AbstractController;

use Db;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Controller\AbstractControllerWithRealHelpers;

final class CountryResolutionTest extends TestCase
{
    private Db $db;

    protected function setUp(): void
    {
        $this->db = $this->createMock(Db::class);
    }

    private function makeController(): AbstractControllerWithRealHelpers
    {
        return new AbstractControllerWithRealHelpers($this->db);
    }

    public function testGetPrestaCountryIdFromIsoReturnsId(): void
    {
        $this->db->method('executeS')
            ->willReturn([['id_country' => '8']]);

        $result = $this->makeController()->exposeGetPrestaCountryId('DE');

        self::assertSame(8, $result);
    }

    public function testGetPrestaCountryIdFromIsoReturnsNullForEmptyResult(): void
    {
        $this->db->method('executeS')
            ->willReturn([]);

        $result = $this->makeController()->exposeGetPrestaCountryId('XX');

        self::assertNull($result);
    }

    public function testGetPrestaCountryIdFromIsoReturnsNullWhenKeyMissing(): void
    {
        $this->db->method('executeS')
            ->willReturn([[]]);

        $result = $this->makeController()->exposeGetPrestaCountryId('YY');

        self::assertNull($result);
    }

    public function testGetDefaultPrestaShopCountryIdReturnsId(): void
    {
        $this->db->method('getValue')
            ->willReturn('8');

        $result = $this->makeController()->exposeGetDefaultCountryId();

        self::assertSame(8, $result);
    }

    public function testGetDefaultPrestaShopCountryIdThrowsWhenNotFound(): void
    {
        $this->db->method('getValue')
            ->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Default country not found');

        $this->makeController()->exposeGetDefaultCountryId();
    }

    public function testGetJtlCountryIsoFromPrestaCountryIdReturnsDE(): void
    {
        $this->db->method('executeS')
            ->willReturn([['iso_code' => 'DE']]);

        $result = $this->makeController()->exposeGetJtlCountryIso(1);

        self::assertSame('DE', $result);
    }

    public function testGetJtlCountryIsoFromPrestaCountryIdReturnsFR(): void
    {
        $this->db->method('executeS')
            ->willReturn([['iso_code' => 'FR']]);

        $result = $this->makeController()->exposeGetJtlCountryIso(2);

        self::assertSame('FR', $result);
    }
}
