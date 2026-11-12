<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CustomerController;

use Address;
use Customer;
use Jtl\Connector\Core\Model\Customer as JtlCustomer;
use Jtl\Connector\Core\Model\Identity;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableCustomerController;
use Tools;

final class CreatePrestaAddressTest extends TestCase
{
    private TestableCustomerController $controller;

    protected function setUp(): void
    {
        Customer::resetMock();
        Tools::resetMock();
        $this->controller = new TestableCustomerController();
    }

    protected function tearDown(): void
    {
        Customer::resetMock();
        Tools::resetMock();
    }

    private function buildFullJtlCustomer(string $countryIso = 'DE'): JtlCustomer
    {
        return (new JtlCustomer())
            ->setId(new Identity('1'))
            ->setCustomerGroupId(new Identity('5'))
            ->setStreet('Musterstraße 1')
            ->setFirstName('Max')
            ->setLastName('Mustermann')
            ->setExtraAddressLine('Apt 2')
            ->setZipCode('12345')
            ->setCity('Berlin')
            ->setPhone('030-123456')
            ->setMobile('0176-123456')
            ->setVatNumber('DE123456789')
            ->setCountryIso($countryIso);
    }

    public function testCreatePrestaAddressMapsAllFieldsToPrestaAddress(): void
    {
        $jtlCustomer = $this->buildFullJtlCustomer('DE');
        $prestaAddr  = new Address();
        $prestaCust  = new Customer();
        $prestaCust->id = 42;

        $result = $this->controller->exposeCreatePrestaAddress($jtlCustomer, $prestaAddr, $prestaCust);

        self::assertSame(42, $result->id_customer);
        self::assertSame('Musterstraße 1', $result->alias);
        self::assertSame('Max', $result->firstname);
        self::assertSame('Mustermann', $result->lastname);
        self::assertSame('Musterstraße 1', $result->address1);
        self::assertSame('Apt 2', $result->address2);
        self::assertSame('12345', $result->postcode);
        self::assertSame('Berlin', $result->city);
        self::assertSame('030-123456', $result->phone);
        self::assertSame('0176-123456', $result->phone_mobile);
        self::assertSame('DE123456789', $result->vat_number);
    }

    public function testCreatePrestaAddressSetsIdCountryWhenKnownCountryIso(): void
    {
        $jtlCustomer = $this->buildFullJtlCustomer('DE'); // 'DE' → 8
        $prestaAddr  = new Address();
        $prestaCust  = new Customer();

        $result = $this->controller->exposeCreatePrestaAddress($jtlCustomer, $prestaAddr, $prestaCust);

        self::assertSame(8, $result->id_country);
    }

    public function testCreatePrestaAddressSetsIdCountryForFranceIso(): void
    {
        $jtlCustomer = $this->buildFullJtlCustomer('FR'); // 'FR' → 9
        $prestaAddr  = new Address();
        $prestaCust  = new Customer();

        $result = $this->controller->exposeCreatePrestaAddress($jtlCustomer, $prestaAddr, $prestaCust);

        self::assertSame(9, $result->id_country);
    }

    public function testCreatePrestaAddressLeavesIdCountryAt0ForUnknownCountryIso(): void
    {
        $jtlCustomer = $this->buildFullJtlCustomer('XX'); // unknown → null from stub
        $prestaAddr  = new Address();
        $prestaCust  = new Customer();

        $result = $this->controller->exposeCreatePrestaAddress($jtlCustomer, $prestaAddr, $prestaCust);

        self::assertSame(0, $result->id_country);
    }

    public function testCreatePrestaAddressBindsIdCustomerFromPrestaCustomer(): void
    {
        $jtlCustomer = $this->buildFullJtlCustomer();
        $prestaAddr  = new Address();
        $prestaCust  = new Customer();
        $prestaCust->id = 99;

        $result = $this->controller->exposeCreatePrestaAddress($jtlCustomer, $prestaAddr, $prestaCust);

        self::assertSame(99, $result->id_customer);
    }

    public function testCreatePrestaAddressWithNullCustomerIdBindsNull(): void
    {
        $jtlCustomer = $this->buildFullJtlCustomer();
        $prestaAddr  = new Address();
        $prestaCust  = new Customer(); // id = null by default from ObjectModel

        $result = $this->controller->exposeCreatePrestaAddress($jtlCustomer, $prestaAddr, $prestaCust);

        self::assertNull($result->id_customer);
    }
}
