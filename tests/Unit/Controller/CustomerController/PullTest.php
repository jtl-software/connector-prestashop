<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CustomerController;

use Db;
use Jtl\Connector\Core\Model\Customer as JtlCustomer;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\QueryFilter;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableCustomerController;

final class PullTest extends TestCase
{
    private TestableCustomerController $controller;

    protected function setUp(): void
    {
        $this->controller = new TestableCustomerController();
    }

    private function makeFullCustomerData(array $overrides = []): array
    {
        return array_merge([
            'id_customer'   => 10,
            'id_group'      => 3,
            'birthday'      => '0000-00-00',
            'city'          => 'Berlin',
            'company'       => 'ACME',
            'iso_code'      => 'DE',
            'date_add'      => '2023-01-01 00:00:00',
            'email'         => 'test@example.com',
            'address2'      => 'Apt 1',
            'firstname'     => 'Max',
            'newsletter'    => 0,
            'active'        => 1,
            'id_lang'       => 1,
            'lastname'      => 'Mustermann',
            'phone_mobile'  => '0176-123',
            'phone'         => '030-123',
            'id_gender'     => 1,
            'website'       => '',
            'address1'      => 'Hauptstr. 1',
            'vat_number'    => '',
            'postcode'      => '10115',
        ], $overrides);
    }

    public function testPullWithEmptyDbResultReturnsEmptyArray(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([]);
        $db->method('escape')->willReturnArgument(0);
        $this->controller->injectDb($db);

        $filter = new QueryFilter();
        $result = $this->controller->pull($filter);

        self::assertSame([], $result);
    }

    public function testPullWithFalseDbResultReturnsEmptyArray(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn(false);
        $db->method('escape')->willReturnArgument(0);
        $this->controller->injectDb($db);

        $filter = new QueryFilter();
        $result = $this->controller->pull($filter);

        self::assertSame([], $result);
    }

    public function testPullWithOneRowReturnsMappedCustomer(): void
    {
        $row = $this->makeFullCustomerData();
        $db  = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([$row]);
        $db->method('escape')->willReturnArgument(0);
        $this->controller->injectDb($db);

        $filter = new QueryFilter();
        $result = $this->controller->pull($filter);

        self::assertCount(1, $result);
        self::assertInstanceOf(JtlCustomer::class, $result[0]);
        self::assertSame((string)$row['id_customer'], $result[0]->getId()->getEndpoint());
    }

    public function testPullWithMultipleRowsReturnsAllMappedCustomers(): void
    {
        $row1 = $this->makeFullCustomerData(['id_customer' => 1, 'email' => 'one@example.com']);
        $row2 = $this->makeFullCustomerData(['id_customer' => 2, 'email' => 'two@example.com']);
        $db   = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([$row1, $row2]);
        $db->method('escape')->willReturnArgument(0);
        $this->controller->injectDb($db);

        $filter = new QueryFilter();
        $result = $this->controller->pull($filter);

        self::assertCount(2, $result);
        self::assertSame('1', $result[0]->getId()->getEndpoint());
        self::assertSame('2', $result[1]->getId()->getEndpoint());
    }
}
