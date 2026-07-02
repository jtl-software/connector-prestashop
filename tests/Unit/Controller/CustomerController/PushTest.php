<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CustomerController;

use Customer;
use Exception;
use Jtl\Connector\Core\Model\Customer as JtlCustomer;
use Jtl\Connector\Core\Model\Identity;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableCustomerController;
use Tools;

final class PushTest extends TestCase
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
        Customer::$mockUpdateResults = [];
    }

    private function makeJtlCustomer(string $endpoint): JtlCustomer
    {
        return (new JtlCustomer())
            ->setId(new Identity($endpoint))
            ->setCustomerGroupId(new Identity('1'))
            ->setFirstName('Max')
            ->setLastName('Mustermann')
            ->setEMail('max@example.com')
            ->setCountryIso('DE')
            ->setCity('Berlin')
            ->setStreet('Hauptstr. 1')
            ->setZipCode('10115')
            ->setLanguageIso('eng')
            ->setSalutation('m')
            ->setCompany('')
            ->setVatNumber('')
            ->setPhone('')
            ->setMobile('')
            ->setWebsiteUrl('');
    }

    public function testPushExistingCustomerReturnsModel(): void
    {
        $jtlCustomer = $this->makeJtlCustomer('42');

        $result = $this->controller->push($jtlCustomer);

        self::assertSame($jtlCustomer, $result);
    }

    public function testPushExistingCustomerWithDifferentEndpointReturnsModel(): void
    {
        $jtlCustomer = $this->makeJtlCustomer('7');
        $result      = $this->controller->push($jtlCustomer);

        self::assertSame($jtlCustomer, $result);
        self::assertSame('7', $result->getId()->getEndpoint());
    }

    public function testPushNewCustomerReturnsModel(): void
    {
        $jtlCustomer = $this->makeJtlCustomer('');
        $result      = $this->controller->push($jtlCustomer);

        self::assertSame($jtlCustomer, $result);
    }

    public function testPushNewCustomerCallsAddNotUpdate(): void
    {
        $jtlCustomer = $this->makeJtlCustomer('');
        $result      = $this->controller->push($jtlCustomer);

        self::assertSame($jtlCustomer, $result);
        self::assertSame('', $result->getId()->getEndpoint());
    }

    public function testPushExistingCustomerThrowsWhenFirstUpdateFails(): void
    {
        Customer::$mockUpdateResults = [false];

        $jtlCustomer = $this->makeJtlCustomer('42');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error updating Customer');

        $this->controller->push($jtlCustomer);
    }

    public function testPushExistingCustomerThrowsWhenSecondUpdateFails(): void
    {
        Customer::$mockUpdateResults = [true, false];

        $jtlCustomer = $this->makeJtlCustomer('42');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error updating address on Customer');

        $this->controller->push($jtlCustomer);
    }
}
