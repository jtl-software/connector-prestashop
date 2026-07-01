<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CustomerController;

use DateTime;
use Jtl\Connector\Core\Model\Customer as JtlCustomer;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableCustomerController;

final class CreateJtlCustomerTest extends TestCase
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

    public function testCreateJtlCustomerMapsAllFields(): void
    {
        $data   = $this->makeFullCustomerData();
        $result = $this->controller->exposeCreateJtlCustomer($data);

        self::assertInstanceOf(JtlCustomer::class, $result);
        self::assertSame('10', $result->getId()->getEndpoint());
        self::assertSame('Berlin', $result->getCity());
        self::assertSame('ACME', $result->getCompany());
        self::assertSame('Max', $result->getFirstName());
        self::assertSame('Mustermann', $result->getLastName());
        self::assertSame('test@example.com', $result->getEMail());
    }

    public function testCreateJtlCustomerWithZeroBirthdayReturnsNullBirthday(): void
    {
        $data   = $this->makeFullCustomerData(['birthday' => '0000-00-00']);
        $result = $this->controller->exposeCreateJtlCustomer($data);

        self::assertNull($result->getBirthday());
    }

    public function testCreateJtlCustomerWithRealBirthdayReturnsBirthdayInstance(): void
    {
        $data   = $this->makeFullCustomerData(['birthday' => '1990-01-15']);
        $result = $this->controller->exposeCreateJtlCustomer($data);

        self::assertNotNull($result->getBirthday());
        self::assertInstanceOf(DateTime::class, $result->getBirthday());
        self::assertSame('1990-01-15', $result->getBirthday()->format('Y-m-d'));
    }
}
