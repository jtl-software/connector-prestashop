<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CustomerController;

use Address;
use Customer;
use Exception;
use Jtl\Connector\Core\Definition\IdentityType;
use Jtl\Connector\Core\Model\Customer as JtlCustomer;
use Jtl\Connector\Core\Model\Identity;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use ObjectModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\Support\AfterHook;
use Tests\Support\Controller\TestableCustomerController;
use Tools;

final class PushTest extends TestCase
{
    private TestableCustomerController $controller;

    private PrimaryKeyMapper&MockObject $mapper;

    protected function setUp(): void
    {
        Customer::resetMock();
        Address::resetMock();
        Tools::resetMock();
        ObjectModel::resetObjectModelMock();
        ObjectModel::$mockNextInsertId = 33;
        $this->controller              = new TestableCustomerController();
        $this->mapper                  = $this->createMock(PrimaryKeyMapper::class);
        $this->controller->injectMapper($this->mapper);
    }

    protected function tearDown(): void
    {
        Customer::resetMock();
        Address::resetMock();
        Tools::resetMock();
        ObjectModel::resetObjectModelMock();
        Customer::$mockUpdateResults = [];
    }

    private function makeJtlCustomer(string $endpoint): JtlCustomer
    {
        return (new JtlCustomer())
            ->setId(new Identity($endpoint, 4001))
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

        [$result] = $this->controller->push($jtlCustomer);

        self::assertSame($jtlCustomer, $result);
    }

    public function testPushExistingCustomerWithDifferentEndpointReturnsModel(): void
    {
        $jtlCustomer = $this->makeJtlCustomer('7');
        [$result] = $this->controller->push($jtlCustomer);

        self::assertSame($jtlCustomer, $result);
        self::assertSame('7', $result->getId()->getEndpoint());
    }

    public function testPushNewCustomerReturnsModel(): void
    {
        $jtlCustomer = $this->makeJtlCustomer('');
        [$result] = $this->controller->push($jtlCustomer);

        self::assertSame($jtlCustomer, $result);
    }

    public function testPushNewCustomerAddsAndLinksTheCustomer(): void
    {
        $this->mapper->expects(self::once())->method('save')->with(IdentityType::CUSTOMER, '33', 4001);

        $jtlCustomer = $this->makeJtlCustomer('');
        [$result] = $this->controller->push($jtlCustomer);

        self::assertSame($jtlCustomer, $result);
        self::assertSame('33', $result->getId()->getEndpoint());
        // a new customer gets its group added, not updated
        self::assertSame(['1'], Customer::$mockAddedGroups);
    }

    public function testPushNewCustomerIsLinkedWhenPsEventbusReadsTheStackInItsHook(): void
    {
        ObjectModel::$mockAfterHook = AfterHook::psEventbusErrorHandler();
        $this->mapper->expects(self::once())->method('save')->with(IdentityType::CUSTOMER, '33', 4001);

        [$result] = $this->controller->push($this->makeJtlCustomer(''));

        self::assertSame('33', $result->getId()->getEndpoint());
    }

    public function testPushNewCustomerIsLinkedAndAWarningLoggedWhenAHookThrowsAfterTheInsert(): void
    {
        ObjectModel::$mockAfterHook = AfterHook::throwing(new \TypeError('ps_eventbus failed'));
        $this->mapper->expects(self::once())->method('save')->with(IdentityType::CUSTOMER, '33', 4001);
        $logger = $this->createMock(LoggerInterface::class);
        // the customer and its address: both are added despite the error
        $logger->expects(self::exactly(2))->method('warning');
        $this->controller->injectLogger($logger);

        [$result] = $this->controller->push($this->makeJtlCustomer(''));

        self::assertSame('33', $result->getId()->getEndpoint());
    }

    public function testPushNewCustomerIsNotLinkedWhenAddFails(): void
    {
        Customer::$mockAddResult = false;
        $this->mapper->expects(self::never())->method('save');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error adding customer');

        $this->controller->push($this->makeJtlCustomer(''));
    }

    public function testPushNewCustomerStaysLinkedWhenItsAddressFails(): void
    {
        // the link is saved right after the customer's INSERT, so the next sync updates it instead of adding it again
        $this->mapper->expects(self::once())->method('save')->with(IdentityType::CUSTOMER, '33', 4001);
        $error                      = new RuntimeException('address failed');
        ObjectModel::$mockAfterHook = static function (?ObjectModel $object) use ($error): void {
            if ($object instanceof Address) {
                $object->id = null; // as if PrestaShop had thrown before the address's INSERT
                throw $error;
            }
        };

        $this->expectExceptionObject($error);

        $this->controller->push($this->makeJtlCustomer(''));
    }

    public function testPushNewCustomerNamesTheRegisteredCustomerWithTheSameEmail(): void
    {
        // PrestaShop 9.1 and later refuse a second registered customer with the same e-mail address
        Customer::$mockAddResult          = false;
        Customer::$mockExistingCustomerId = 7;
        $this->mapper->expects(self::never())->method('save');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already has a registered customer with this e-mail address (id 7)');

        $this->controller->push($this->makeJtlCustomer(''));
    }

    public function testPushNewCustomerFailsWhenItsAddressIsNotAdded(): void
    {
        Address::$mockAddResult = false;
        // still linked: the next sync updates the customer and adds the address
        $this->mapper->expects(self::once())->method('save')->with(IdentityType::CUSTOMER, '33', 4001);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error adding the address of customer');

        $this->controller->push($this->makeJtlCustomer(''));
    }

    public function testPushExistingCustomerWithoutAddressGetsItsAddress(): void
    {
        // e.g. a customer linked by a push that then failed on its address
        Address::$mockFirstCustomerAddressId = 0;

        $this->controller->push($this->makeJtlCustomer('42'));

        self::assertSame(1, Address::$mockAddCalls);
    }

    public function testPushExistingCustomerWithAddressAddsNoOther(): void
    {
        Address::$mockFirstCustomerAddressId = 7;

        $this->controller->push($this->makeJtlCustomer('42'));

        self::assertSame(0, Address::$mockAddCalls);
    }

    public function testPushCustomerDeletedInTheShopAddsNoAddress(): void
    {
        // the linked customer 42 was deleted in PrestaShop: an address would have no customer
        Customer::$mockMissingIds = [42];

        $this->controller->push($this->makeJtlCustomer('42'));

        self::assertSame(0, Address::$mockAddCalls);
    }

    public function testPushSoftDeletedCustomerAddsNoAddress(): void
    {
        // PrestaShop deletes its addresses on every update, so an address would be added on every push
        Customer::$mockLoadedDeleted = true;

        $this->controller->push($this->makeJtlCustomer('42'));

        self::assertSame(0, Address::$mockAddCalls);
    }

    public function testUpdatesAndDeletesWithoutStackFramesThatPsEventbusCannotRead(): void
    {
        ObjectModel::$mockAfterHook = AfterHook::psEventbusErrorHandler();
        $jtlCustomer                = $this->makeJtlCustomer('42');

        self::assertSame([$jtlCustomer], $this->controller->push($jtlCustomer));
        self::assertSame([$jtlCustomer], $this->controller->delete($jtlCustomer));
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
