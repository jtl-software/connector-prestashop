<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\ProductController;

use Db;
use Jtl\Connector\Core\Model\ProductVariation as JtlProductVariation;
use Jtl\Connector\Core\Model\ProductVariationValue as JtlProductVariationValue;
use Jtl\Connector\Core\Model\ProductVariationValueI18n as JtlProductVariationValueI18n;
use jtl\Connector\Presta\Controller\ProductController;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Exposes the variation lookups and returns fixed translations, so only the SQL built from the names is under test.
 */
final class AttributeLookupProductController extends ProductController
{
    public function __construct(Db $db)
    {
        $this->db             = $db;
        $this->logger         = new NullLogger();
        $this->controllerName = 'ProductController';
    }

    public function exposeCreatePrestaAttributeGroup(JtlProductVariation $jtlVariation): int
    {
        return $this->createPrestaAttributeGroup($jtlVariation);
    }

    public function exposeCreatePrestaAttribute(JtlProductVariationValue $jtlValue, int $prestaAttributeGroupId): int
    {
        return $this->createPrestaAttribute($jtlValue, $prestaAttributeGroupId);
    }

    protected function createPrestaAttributeGroupTranslations(JtlProductVariation $jtlVariation, string $groupType): array
    {
        return [1 => ['name' => "Men's size (Select)", 'public_name' => "Men's size"]];
    }

    protected function createPrestaAttributeTranslations(JtlProductVariationValue $jtlValue): array
    {
        return [1 => ['name' => "Men's"]];
    }
}

/**
 * Variation and variation value names come from JTL-Wawi and are looked up by name: they must be escaped, otherwise
 * a name with an apostrophe breaks the lookup and every push creates another attribute group or attribute.
 */
final class AttributeLookupTest extends TestCase
{
    private ?string $capturedSql = null;

    private function controller(): AttributeLookupProductController
    {
        $db = $this->createMock(Db::class);
        $db->method('getValue')
            ->willReturnCallback(function (mixed $sql): bool {
                $this->capturedSql = (string)$sql;
                return false;
            });

        return new AttributeLookupProductController($db);
    }

    public function testAttributeGroupLookupEscapesTheName(): void
    {
        $this->controller()->exposeCreatePrestaAttributeGroup(
            (new JtlProductVariation())->setType(JtlProductVariation::TYPE_SELECT)
        );

        self::assertNotNull($this->capturedSql);
        // the PrestaShop stub's pSQL() is addslashes()
        self::assertStringContainsString("name = 'Men\\'s size (Select)'", $this->capturedSql);
    }

    public function testAttributeLookupEscapesTheName(): void
    {
        $jtlValue = (new JtlProductVariationValue())
            ->setI18ns((new JtlProductVariationValueI18n())->setName("Men's")->setLanguageIso('ger'));

        $this->controller()->exposeCreatePrestaAttribute($jtlValue, 7);

        self::assertNotNull($this->capturedSql);
        self::assertStringContainsString("l.name = 'Men\\'s'", $this->capturedSql);
        self::assertStringContainsString('a.id_attribute_group = 7', $this->capturedSql);
    }
}
