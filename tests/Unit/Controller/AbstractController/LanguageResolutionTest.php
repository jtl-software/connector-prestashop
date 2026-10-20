<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\AbstractController;

use Db;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Controller\AbstractControllerWithRealHelpers;

final class LanguageResolutionTest extends TestCase
{
    private Db $db;

    protected function setUp(): void
    {
        $this->db = $this->createMock(Db::class);
        Db::resetInstance();
    }

    private function makeController(): AbstractControllerWithRealHelpers
    {
        return new AbstractControllerWithRealHelpers($this->db);
    }

    public function testGetJtlLangIsoFromLanguageIdReturnsGerForDeDe(): void
    {
        $this->db->method('executeS')
            ->willReturn([['language_code' => 'de-DE']]);

        $result = $this->makeController()->exposeGetJtlLang(1);

        self::assertSame('ger', $result);
    }

    public function testGetJtlLangIsoFromLanguageIdReturnsEngForEnUs(): void
    {
        $this->db->method('executeS')
            ->willReturn([['language_code' => 'en-US']]);

        $result = $this->makeController()->exposeGetJtlLang(2);

        self::assertSame('eng', $result);
    }

    public function testGetPrestaLangIdFromIso2CharReturnsId(): void
    {
        $this->db->method('executeS')
            ->willReturn([['id_lang' => '1']]);

        $result = $this->makeController()->exposeGetPrestaLangId('de');

        self::assertSame(1, $result);
    }

    public function testGetPrestaLangIdFromIso3CharConvertsAndReturnsId(): void
    {
        $this->db->method('executeS')
            ->willReturn([['id_lang' => '2']]);

        // 'ger' is ISO 639-2b for German; Lingua should convert it to 'de'
        $result = $this->makeController()->exposeGetPrestaLangId('ger');

        self::assertSame(2, $result);
    }

    public function testGetPrestaLangIdFromIsoThrowsWhenIdLangIsNull(): void
    {
        $this->db->method('executeS')
            ->willReturn([['id_lang' => null]]);

        $this->expectException(RuntimeException::class);

        $this->makeController()->exposeGetPrestaLangId('de');
    }
}
