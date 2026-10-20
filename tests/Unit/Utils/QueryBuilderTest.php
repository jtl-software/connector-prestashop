<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use jtl\Connector\Presta\Utils\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class QueryBuilderTest extends TestCase
{
    private function newQb(): QueryBuilder
    {
        return new QueryBuilder();
    }

    // -------------------------------------------------------------------------
    // from()
    // -------------------------------------------------------------------------

    public function testFromWithPrefixEnabledPrependsDatabasePrefix(): void
    {
        $qb = $this->newQb();
        $qb->from('products');
        self::assertContains('`ps_products`', $qb->getQuery()['from']);
    }

    public function testFromWithPrefixEnabledAndAliasAppendsAlias(): void
    {
        $qb = $this->newQb();
        $qb->from('products', 'p');
        self::assertContains('`ps_products` p', $qb->getQuery()['from']);
    }

    public function testFromWithPrefixDisabledOmitsDatabasePrefix(): void
    {
        $qb = $this->newQb();
        $qb->setUsePrefix(false);
        $qb->from('products');
        self::assertContains('`products`', $qb->getQuery()['from']);
    }

    public function testFromWithPrefixDisabledAndAliasAppendsAlias(): void
    {
        $qb = $this->newQb();
        $qb->setUsePrefix(false);
        $qb->from('products', 'p');
        self::assertContains('`products` p', $qb->getQuery()['from']);
    }

    public function testFromWithSubQueryInstanceWrapsSubQueryInParentheses(): void
    {
        $sub = $this->newQb();
        $sub->select('1');
        $expectedInner = '(' . $sub->build() . ')';

        $qb = $this->newQb();
        $qb->from($sub);
        self::assertContains($expectedInner, $qb->getQuery()['from']);
    }

    public function testFromWithSubQueryInstanceAndPrefixDisabledWrapsSubQueryInParentheses(): void
    {
        $sub = $this->newQb();
        $sub->select('id');
        $expectedInner = '(' . $sub->build() . ')';

        $qb = $this->newQb();
        $qb->setUsePrefix(false);
        $qb->from($sub);
        self::assertContains($expectedInner, $qb->getQuery()['from']);
    }

    public function testFromWithEmptyStringLeavesFromArrayEmpty(): void
    {
        $qb = $this->newQb();
        $qb->from('');
        self::assertEmpty($qb->getQuery()['from']);
    }

    public function testFromWithEmptyStringAndPrefixDisabledLeavesFromArrayEmpty(): void
    {
        $qb = $this->newQb();
        $qb->setUsePrefix(false);
        $qb->from('');
        self::assertEmpty($qb->getQuery()['from']);
    }

    // -------------------------------------------------------------------------
    // leftJoin()
    // -------------------------------------------------------------------------

    public function testLeftJoinWithPrefixEnabledIncludesDatabasePrefix(): void
    {
        $qb = $this->newQb();
        $qb->leftJoin('product', 'p', 'p.id = x.id');
        $join = implode(' ', $qb->getQuery()['join']);
        self::assertStringContainsString('LEFT JOIN `ps_product`', $join);
        self::assertStringContainsString('`p`', $join);
        self::assertStringContainsString('ON p.id = x.id', $join);
    }

    public function testLeftJoinWithPrefixDisabledOmitsDatabasePrefix(): void
    {
        $qb = $this->newQb();
        $qb->setUsePrefix(false);
        $qb->leftJoin('product', 'p', 'p.id = x.id');
        $join = implode(' ', $qb->getQuery()['join']);
        self::assertStringContainsString('LEFT JOIN `product`', $join);
        self::assertStringNotContainsString('ps_', $join);
    }

    public function testLeftJoinWithoutAliasAndOnClauseContainsNeitherAliasNorOnKeyword(): void
    {
        $qb = $this->newQb();
        $qb->leftJoin('product');
        $join = implode(' ', $qb->getQuery()['join']);
        self::assertStringContainsString('LEFT JOIN `ps_product`', $join);
        self::assertStringNotContainsString('ON', $join);
        // No alias backtick pair after the table backtick
        self::assertStringNotContainsString('`` `', $join);
    }

    // -------------------------------------------------------------------------
    // innerJoin()
    // -------------------------------------------------------------------------

    public function testInnerJoinWithPrefixEnabledIncludesDatabasePrefix(): void
    {
        $qb = $this->newQb();
        $qb->innerJoin('order', 'o', 'o.id = p.id_order');
        $join = implode(' ', $qb->getQuery()['join']);
        self::assertStringContainsString('INNER JOIN `ps_order`', $join);
        self::assertStringContainsString('ON o.id = p.id_order', $join);
    }

    public function testInnerJoinWithPrefixDisabledOmitsDatabasePrefix(): void
    {
        $qb = $this->newQb();
        $qb->setUsePrefix(false);
        $qb->innerJoin('order', 'o', 'o.id = p.id_order');
        $join = implode(' ', $qb->getQuery()['join']);
        self::assertStringContainsString('INNER JOIN `order`', $join);
        self::assertStringNotContainsString('ps_', $join);
    }

    public function testInnerJoinWithoutAliasAndOnClauseContainsNeitherAliasNorOn(): void
    {
        $qb = $this->newQb();
        $qb->innerJoin('order');
        $join = implode(' ', $qb->getQuery()['join']);
        self::assertStringContainsString('INNER JOIN `ps_order`', $join);
        self::assertStringNotContainsString('ON', $join);
    }

    // -------------------------------------------------------------------------
    // leftOuterJoin()
    // -------------------------------------------------------------------------

    public function testLeftOuterJoinWithPrefixEnabledIncludesDatabasePrefix(): void
    {
        $qb = $this->newQb();
        $qb->leftOuterJoin('category', 'c', 'c.id = p.id_category');
        $join = implode(' ', $qb->getQuery()['join']);
        self::assertStringContainsString('LEFT OUTER JOIN `ps_category`', $join);
        self::assertStringContainsString('ON c.id = p.id_category', $join);
    }

    public function testLeftOuterJoinWithPrefixDisabledOmitsDatabasePrefix(): void
    {
        $qb = $this->newQb();
        $qb->setUsePrefix(false);
        $qb->leftOuterJoin('category', 'c', 'c.id = p.id_category');
        $join = implode(' ', $qb->getQuery()['join']);
        self::assertStringContainsString('LEFT OUTER JOIN `category`', $join);
        self::assertStringNotContainsString('ps_', $join);
    }

    public function testLeftOuterJoinWithoutAliasAndOnClauseContainsNeitherAliasNorOn(): void
    {
        $qb = $this->newQb();
        $qb->leftOuterJoin('category');
        $join = implode(' ', $qb->getQuery()['join']);
        self::assertStringContainsString('LEFT OUTER JOIN `ps_category`', $join);
        self::assertStringNotContainsString('ON', $join);
    }

    // -------------------------------------------------------------------------
    // naturalJoin()
    // -------------------------------------------------------------------------

    public function testNaturalJoinWithPrefixEnabledIncludesDatabasePrefix(): void
    {
        $qb = $this->newQb();
        $qb->naturalJoin('lang');
        $join = implode(' ', $qb->getQuery()['join']);
        self::assertStringContainsString('NATURAL JOIN `ps_lang`', $join);
    }

    public function testNaturalJoinWithPrefixDisabledOmitsDatabasePrefix(): void
    {
        $qb = $this->newQb();
        $qb->setUsePrefix(false);
        $qb->naturalJoin('lang');
        $join = implode(' ', $qb->getQuery()['join']);
        self::assertStringContainsString('NATURAL JOIN `lang`', $join);
        self::assertStringNotContainsString('ps_', $join);
    }

    public function testNaturalJoinWithAliasAppendsAliasInBackticks(): void
    {
        $qb = $this->newQb();
        $qb->naturalJoin('lang', 'l');
        $join = implode(' ', $qb->getQuery()['join']);
        self::assertStringContainsString('`l`', $join);
    }

    // -------------------------------------------------------------------------
    // rightJoin()
    // -------------------------------------------------------------------------

    public function testRightJoinWithPrefixEnabledIncludesDatabasePrefix(): void
    {
        $qb = $this->newQb();
        $qb->rightJoin('shop', 's', 's.id = p.id_shop');
        $join = implode(' ', $qb->getQuery()['join']);
        self::assertStringContainsString('RIGHT JOIN `ps_shop`', $join);
        self::assertStringContainsString('ON s.id = p.id_shop', $join);
    }

    public function testRightJoinWithPrefixDisabledOmitsDatabasePrefix(): void
    {
        $qb = $this->newQb();
        $qb->setUsePrefix(false);
        $qb->rightJoin('shop', 's', 's.id = p.id_shop');
        $join = implode(' ', $qb->getQuery()['join']);
        self::assertStringContainsString('RIGHT JOIN `shop`', $join);
        self::assertStringNotContainsString('ps_', $join);
    }

    public function testRightJoinWithoutAliasAndOnClauseContainsNeitherAliasNorOn(): void
    {
        $qb = $this->newQb();
        $qb->rightJoin('shop');
        $join = implode(' ', $qb->getQuery()['join']);
        self::assertStringContainsString('RIGHT JOIN `ps_shop`', $join);
        self::assertStringNotContainsString('ON', $join);
    }

    // -------------------------------------------------------------------------
    // orderBy()
    // -------------------------------------------------------------------------

    public function testOrderByWithStringAddsOneEntryToOrderArray(): void
    {
        $qb = $this->newQb();
        $qb->orderBy('price DESC');
        self::assertSame(['price DESC'], $qb->getQuery()['order']);
    }

    public function testOrderByWithArrayMergesAllFieldsIntoOrderArray(): void
    {
        $qb = $this->newQb();
        $qb->orderBy(['price DESC', 'name ASC']);
        self::assertSame(['price DESC', 'name ASC'], $qb->getQuery()['order']);
    }

    public function testOrderByWithEmptyStringLeavesOrderArrayEmpty(): void
    {
        $qb = $this->newQb();
        $qb->orderBy('');
        self::assertEmpty($qb->getQuery()['order']);
    }

    public function testOrderByWithEmptyArrayLeavesOrderArrayEmpty(): void
    {
        $qb = $this->newQb();
        $qb->orderBy([]);
        self::assertEmpty($qb->getQuery()['order']);
    }

    public function testOrderByCalledMultipleTimesAccumulatesEntries(): void
    {
        $qb = $this->newQb();
        $qb->orderBy('price DESC');
        $qb->orderBy('name ASC');
        self::assertSame(['price DESC', 'name ASC'], $qb->getQuery()['order']);
    }

    // -------------------------------------------------------------------------
    // groupBy()
    // -------------------------------------------------------------------------

    public function testGroupByWithStringAddsOneEntryToGroupArray(): void
    {
        $qb = $this->newQb();
        $qb->groupBy('id_category');
        self::assertSame(['id_category'], $qb->getQuery()['group']);
    }

    public function testGroupByWithArrayMergesAllFieldsIntoGroupArray(): void
    {
        $qb = $this->newQb();
        $qb->groupBy(['id_category', 'id_shop']);
        self::assertSame(['id_category', 'id_shop'], $qb->getQuery()['group']);
    }

    public function testGroupByWithEmptyStringLeavesGroupArrayEmpty(): void
    {
        $qb = $this->newQb();
        $qb->groupBy('');
        self::assertEmpty($qb->getQuery()['group']);
    }

    public function testGroupByWithEmptyArrayLeavesGroupArrayEmpty(): void
    {
        $qb = $this->newQb();
        $qb->groupBy([]);
        self::assertEmpty($qb->getQuery()['group']);
    }

    // -------------------------------------------------------------------------
    // limit()
    // -------------------------------------------------------------------------

    public function testLimitWithOnlyLimitArgSetsLimitAndDefaultsOffsetToZero(): void
    {
        $qb = $this->newQb();
        $qb->limit(10);
        $limit = $qb->getQuery()['limit'];
        self::assertSame(10, $limit['limit']);
        self::assertSame(0, $limit['offset']);
    }

    public function testLimitWithExplicitOffsetSetsLimitAndOffset(): void
    {
        $qb = $this->newQb();
        $qb->limit(10, 5);
        $limit = $qb->getQuery()['limit'];
        self::assertSame(10, $limit['limit']);
        self::assertSame(5, $limit['offset']);
    }

    public function testLimitWithNegativeOffsetClampsOffsetToZero(): void
    {
        $qb = $this->newQb();
        $qb->limit(10, -3);
        $limit = $qb->getQuery()['limit'];
        self::assertSame(10, $limit['limit']);
        self::assertSame(0, $limit['offset']);
    }

    public function testLimitWithZeroOffsetKeepsOffsetAtZero(): void
    {
        $qb = $this->newQb();
        $qb->limit(20, 0);
        $limit = $qb->getQuery()['limit'];
        self::assertSame(20, $limit['limit']);
        self::assertSame(0, $limit['offset']);
    }

    // -------------------------------------------------------------------------
    // setUsePrefix()
    // -------------------------------------------------------------------------

    public function testSetUsePrefixFalseDisablesPrefixForSubsequentFromCall(): void
    {
        $qb = $this->newQb();
        $qb->setUsePrefix(false);
        $qb->from('products');
        self::assertContains('`products`', $qb->getQuery()['from']);
        foreach ($qb->getQuery()['from'] as $entry) {
            self::assertStringNotContainsString('ps_', $entry);
        }
    }

    public function testSetUsePrefixTrueRestoresPrefixBehaviour(): void
    {
        $qb = $this->newQb();
        $qb->setUsePrefix(false);
        $qb->setUsePrefix(true);
        $qb->from('products');
        self::assertContains('`ps_products`', $qb->getQuery()['from']);
    }

    // -------------------------------------------------------------------------
    // Fluent interface – all relevant methods return the same QueryBuilder instance
    // -------------------------------------------------------------------------

    public function testFromReturnsQueryBuilderInstanceForFluentChaining(): void
    {
        $qb = $this->newQb();
        self::assertInstanceOf(QueryBuilder::class, $qb->from('products'));
    }

    public function testLeftJoinReturnsQueryBuilderInstanceForFluentChaining(): void
    {
        $qb = $this->newQb();
        self::assertInstanceOf(QueryBuilder::class, $qb->leftJoin('product'));
    }

    public function testInnerJoinReturnsQueryBuilderInstanceForFluentChaining(): void
    {
        $qb = $this->newQb();
        self::assertInstanceOf(QueryBuilder::class, $qb->innerJoin('order'));
    }

    public function testLeftOuterJoinReturnsQueryBuilderInstanceForFluentChaining(): void
    {
        $qb = $this->newQb();
        self::assertInstanceOf(QueryBuilder::class, $qb->leftOuterJoin('category'));
    }

    public function testNaturalJoinReturnsQueryBuilderInstanceForFluentChaining(): void
    {
        $qb = $this->newQb();
        self::assertInstanceOf(QueryBuilder::class, $qb->naturalJoin('lang'));
    }

    public function testRightJoinReturnsQueryBuilderInstanceForFluentChaining(): void
    {
        $qb = $this->newQb();
        self::assertInstanceOf(QueryBuilder::class, $qb->rightJoin('shop'));
    }

    public function testOrderByReturnsQueryBuilderInstanceForFluentChaining(): void
    {
        $qb = $this->newQb();
        self::assertInstanceOf(QueryBuilder::class, $qb->orderBy('price'));
    }

    public function testGroupByReturnsQueryBuilderInstanceForFluentChaining(): void
    {
        $qb = $this->newQb();
        self::assertInstanceOf(QueryBuilder::class, $qb->groupBy('id'));
    }

    public function testLimitReturnsQueryBuilderInstanceForFluentChaining(): void
    {
        $qb = $this->newQb();
        self::assertInstanceOf(QueryBuilder::class, $qb->limit(10));
    }

    public function testFullFluentChainBuildsExpectedSql(): void
    {
        $qb = $this->newQb();
        $sql = $qb
            ->select('p.id_product, p.reference')
            ->from('product', 'p')
            ->leftJoin('category_product', 'cp', 'cp.id_product = p.id_product')
            ->where('p.active = 1')
            ->orderBy('p.id_product ASC')
            ->groupBy('p.id_product')
            ->limit(10, 0)
            ->build();

        self::assertStringContainsString('ps_product', $sql);
        self::assertStringContainsString('LEFT JOIN', $sql);
        self::assertStringContainsString('WHERE', $sql);
        self::assertStringContainsString('ORDER BY', $sql);
        self::assertStringContainsString('GROUP BY', $sql);
        self::assertStringContainsString('LIMIT', $sql);
    }
}
