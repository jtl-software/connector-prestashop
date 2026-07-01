<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CustomerOrder;

use Address;
use Customer;
use Jtl\Connector\Core\Model\CustomerOrderBillingAddress as JtlBillingAddress;
use Jtl\Connector\Core\Model\CustomerOrderShippingAddress as JtlShippingAddress;
use RuntimeException;

/**
 * Covers address mapping from PrestaShop Address + Customer to JTL address models.
 *
 * Tests:
 *   - createJtlCustomerOrderBillingAddress  (field mapping + salutation + TypeError path)
 *   - createJtlCustomerOrderShippingAddress (field mapping + salutation + TypeError path)
 */
final class CustomerOrderAddressTest extends CustomerOrderControllerTestCase
{
    private function makeAddress(array $overrides = []): Address
    {
        $addr               = new Address();
        $addr->id_country   = 1;
        $addr->city         = 'Berlin';
        $addr->company      = 'ACME GmbH';
        $addr->address1     = 'Hauptstr. 1';
        $addr->address2     = 'Etage 2';
        $addr->postcode     = '10115';
        $addr->firstname    = 'Max';
        $addr->lastname     = 'Mustermann';
        $addr->phone        = '+49301234567';
        $addr->phone_mobile = '+491701234567';
        $addr->vat_number   = 'DE123456789';
        foreach ($overrides as $k => $v) {
            $addr->$k = $v;
        }
        return $addr;
    }

    private function makeCustomer(int $idGender = 1, string $email = 'test@example.com'): Customer
    {
        $cust            = new Customer();
        $cust->id_gender = $idGender;
        $cust->email     = $email;
        return $cust;
    }

    // -------------------------------------------------------------------------
    // createJtlCustomerOrderBillingAddress
    // -------------------------------------------------------------------------

    public function testBillingAddressMapsAllFields(): void
    {
        $result = $this->controller->exposeCreateJtlCustomerOrderBillingAddress(
            $this->makeAddress(),
            $this->makeCustomer(1, 'john@example.com')
        );

        self::assertInstanceOf(JtlBillingAddress::class, $result);
        self::assertSame('Berlin', $result->getCity());
        self::assertSame('ACME GmbH', $result->getCompany());
        self::assertSame('DE', $result->getCountryIso());
        self::assertSame('john@example.com', $result->getEMail());
        self::assertSame('Etage 2', $result->getExtraAddressLine());
        self::assertSame('Max', $result->getFirstName());
        self::assertSame('Mustermann', $result->getLastName());
        self::assertSame('+491701234567', $result->getMobile());
        self::assertSame('+49301234567', $result->getPhone());
        self::assertSame('Hauptstr. 1', $result->getStreet());
        self::assertSame('10115', $result->getZipCode());
        self::assertSame('DE123456789', $result->getVatNumber());
        self::assertSame('m', $result->getSalutation());
    }

    public function testBillingAddressSalutationForFemaleCustomer(): void
    {
        $result = $this->controller->exposeCreateJtlCustomerOrderBillingAddress(
            $this->makeAddress(),
            $this->makeCustomer(2)
        );

        self::assertSame('w', $result->getSalutation());
    }

    public function testBillingAddressEmailComesFromCustomerNotAddress(): void
    {
        $result = $this->controller->exposeCreateJtlCustomerOrderBillingAddress(
            $this->makeAddress(),
            $this->makeCustomer(1, 'customer@shop.com')
        );

        self::assertSame('customer@shop.com', $result->getEMail());
    }

    public function testBillingAddressTypeErrorIsWrappedInRuntimeException(): void
    {
        $controller = new TestableCustomerOrderControllerForTypeError();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Error while creating Billing Address/');

        $controller->exposeCreateJtlCustomerOrderBillingAddress($this->makeAddress(), $this->makeCustomer());
    }

    public function testBillingAddressTypeErrorMessageContainsCustomerId(): void
    {
        $controller      = new TestableCustomerOrderControllerForTypeError();
        $cust            = $this->makeCustomer();
        $cust->id        = 42;

        try {
            $controller->exposeCreateJtlCustomerOrderBillingAddress($this->makeAddress(), $cust);
            self::fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('42', $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // createJtlCustomerOrderShippingAddress
    // -------------------------------------------------------------------------

    public function testShippingAddressMapsAllFields(): void
    {
        $result = $this->controller->exposeCreateJtlCustomerOrderShippingAddress(
            $this->makeAddress(),
            $this->makeCustomer(1, 'ship@example.com')
        );

        self::assertInstanceOf(JtlShippingAddress::class, $result);
        self::assertSame('Berlin', $result->getCity());
        self::assertSame('ACME GmbH', $result->getCompany());
        self::assertSame('DE', $result->getCountryIso());
        self::assertSame('ship@example.com', $result->getEMail());
        self::assertSame('Etage 2', $result->getExtraAddressLine());
        self::assertSame('Max', $result->getFirstName());
        self::assertSame('Mustermann', $result->getLastName());
        self::assertSame('+491701234567', $result->getMobile());
        self::assertSame('+49301234567', $result->getPhone());
        self::assertSame('Hauptstr. 1', $result->getStreet());
        self::assertSame('10115', $result->getZipCode());
        self::assertSame('m', $result->getSalutation());
    }

    public function testShippingAddressSalutationForFemaleCustomer(): void
    {
        $result = $this->controller->exposeCreateJtlCustomerOrderShippingAddress(
            $this->makeAddress(),
            $this->makeCustomer(2)
        );

        self::assertSame('w', $result->getSalutation());
    }

    public function testShippingAddressEmptyOptionalFieldsPassedThrough(): void
    {
        $result = $this->controller->exposeCreateJtlCustomerOrderShippingAddress(
            $this->makeAddress(['company' => '', 'address2' => '', 'phone_mobile' => '']),
            $this->makeCustomer()
        );

        self::assertSame('', $result->getCompany());
        self::assertSame('', $result->getExtraAddressLine());
        self::assertSame('', $result->getMobile());
    }

    public function testShippingAddressTypeErrorIsWrappedInRuntimeException(): void
    {
        $controller = new TestableCustomerOrderControllerForTypeError();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Error while creating Shipping Address/');

        $controller->exposeCreateJtlCustomerOrderShippingAddress($this->makeAddress(), $this->makeCustomer());
    }

    public function testShippingAddressTypeErrorMessageContainsCustomerId(): void
    {
        $controller   = new TestableCustomerOrderControllerForTypeError();
        $cust         = $this->makeCustomer();
        $cust->id     = 99;

        try {
            $controller->exposeCreateJtlCustomerOrderShippingAddress($this->makeAddress(), $cust);
            self::fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('99', $e->getMessage());
        }
    }
}
