<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CustomerController;

use Customer;
use Jtl\Connector\Core\Model\Customer as JtlCustomer;
use Jtl\Connector\Core\Model\Identity;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableCustomerController;

final class ChangeCustomerGroupTest extends TestCase
{
    private TestableCustomerController $controller;

    protected function setUp(): void
    {
        $this->controller = new TestableCustomerController();
    }

    public function testChangeCustomerGroupCallsAddGroupsWhenIsNewIsTrue(): void
    {
        $jtlCustomer = (new JtlCustomer())
            ->setCustomerGroupId(new Identity('3'));

        $mockPrestaCustomer = $this->createMock(Customer::class);
        $mockPrestaCustomer
            ->expects(self::once())
            ->method('addGroups')
            ->with(['3']);

        $mockPrestaCustomer
            ->expects(self::never())
            ->method('updateGroup');

        $this->controller->exposeChangeCustomerGroup($jtlCustomer, $mockPrestaCustomer, true);
    }

    public function testChangeCustomerGroupCallsUpdateGroupWhenIsNewIsFalse(): void
    {
        $jtlCustomer = (new JtlCustomer())
            ->setCustomerGroupId(new Identity('7'));

        $mockPrestaCustomer = $this->createMock(Customer::class);
        $mockPrestaCustomer
            ->expects(self::never())
            ->method('addGroups');

        $mockPrestaCustomer
            ->expects(self::once())
            ->method('updateGroup')
            ->with(['7']);

        $this->controller->exposeChangeCustomerGroup($jtlCustomer, $mockPrestaCustomer, false);
    }

    public function testChangeCustomerGroupPassesCorrectGroupEndpointToAddGroups(): void
    {
        $jtlCustomer = (new JtlCustomer())
            ->setCustomerGroupId(new Identity('group-endpoint-42'));

        $mockPrestaCustomer = $this->createMock(Customer::class);
        $mockPrestaCustomer
            ->expects(self::once())
            ->method('addGroups')
            ->with(['group-endpoint-42']);

        $this->controller->exposeChangeCustomerGroup($jtlCustomer, $mockPrestaCustomer, true);
    }

    public function testChangeCustomerGroupPassesCorrectGroupEndpointToUpdateGroup(): void
    {
        $jtlCustomer = (new JtlCustomer())
            ->setCustomerGroupId(new Identity('group-endpoint-99'));

        $mockPrestaCustomer = $this->createMock(Customer::class);
        $mockPrestaCustomer
            ->expects(self::once())
            ->method('updateGroup')
            ->with(['group-endpoint-99']);

        $this->controller->exposeChangeCustomerGroup($jtlCustomer, $mockPrestaCustomer, false);
    }
}
