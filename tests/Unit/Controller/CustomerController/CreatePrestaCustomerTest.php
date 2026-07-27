<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CustomerController;

use Customer;
use DateTime;
use Jtl\Connector\Core\Model\Customer as JtlCustomer;
use PHPUnit\Framework\TestCase;
use PrestaShop\PrestaShop\Core\Foundation\IoC\Exception;
use Tests\Support\Controller\TestableCustomerController;
use Tools;

final class CreatePrestaCustomerTest extends TestCase
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

    public function testCreatePrestaCustomerSetsAllFields(): void
    {
        $jtlCustomer = (new JtlCustomer())
            ->setFirstName('Hans')
            ->setLastName('Schmidt')
            ->setEMail('hans@example.com')
            ->setCompany('GmbH')
            ->setHasNewsletterSubscription(true)
            ->setIsActive(true)
            ->setLanguageIso('deu')
            ->setSalutation('m')
            ->setWebsiteUrl('https://example.com');

        $prestaCustomer = new Customer();
        $result         = $this->controller->exposeCreatePrestaCustomer($jtlCustomer, $prestaCustomer);

        self::assertSame('Hans', $result->firstname);
        self::assertSame('Schmidt', $result->lastname);
        self::assertSame('hans@example.com', $result->email);
        self::assertSame('GmbH', $result->company);
        self::assertTrue($result->newsletter);
        self::assertTrue($result->active);
        self::assertSame(1, $result->id_gender); // salutation 'm' → 1
    }

    public function testCreatePrestaCustomerWithMaleSalutationSetsGender1(): void
    {
        $jtlCustomer = (new JtlCustomer())
            ->setSalutation('m')
            ->setLanguageIso('eng');
        $prestaCustomer = new Customer();
        $result         = $this->controller->exposeCreatePrestaCustomer($jtlCustomer, $prestaCustomer);
        self::assertSame(1, $result->id_gender);
    }

    public function testCreatePrestaCustomerWithNonMaleSalutationSetsGender0(): void
    {
        $jtlCustomer = (new JtlCustomer())
            ->setSalutation('w')
            ->setLanguageIso('eng');
        $prestaCustomer = new Customer();
        $result         = $this->controller->exposeCreatePrestaCustomer($jtlCustomer, $prestaCustomer);
        self::assertSame(0, $result->id_gender);
    }

    public function testCreatePrestaCustomerUsesExistingPasswordWhenSet(): void
    {
        $jtlCustomer = (new JtlCustomer())
            ->setLanguageIso('eng');
        $prestaCustomer         = new Customer();
        $prestaCustomer->passwd = 'existing-hash';
        $result                 = $this->controller->exposeCreatePrestaCustomer($jtlCustomer, $prestaCustomer);
        self::assertSame('existing-hash', $result->passwd);
    }

    public function testCreatePrestaCustomerWithBirthdaySetsBirthdayField(): void
    {
        $jtlCustomer = (new JtlCustomer())
            ->setBirthday(new DateTime('1985-06-20'))
            ->setLanguageIso('eng');
        $prestaCustomer = new Customer();
        $result         = $this->controller->exposeCreatePrestaCustomer($jtlCustomer, $prestaCustomer);
        self::assertSame('1985-06-20', $result->birthday);
    }

    public function testCreatePrestaCustomerThrowsWhenPasswdGenReturnsNull(): void
    {
        Tools::$mockPasswdGenNull = true;

        $jtlCustomer    = (new JtlCustomer())
            ->setLanguageIso('eng');
        $prestaCustomer = new Customer();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error generating password');

        $this->controller->exposeCreatePrestaCustomer($jtlCustomer, $prestaCustomer);
    }
}
