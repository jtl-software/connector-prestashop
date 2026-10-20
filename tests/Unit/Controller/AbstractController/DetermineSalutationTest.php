<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\AbstractController;

use Customer;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\ConcreteAbstractController;

final class DetermineSalutationTest extends TestCase
{
    private ConcreteAbstractController $controller;

    protected function setUp(): void
    {
        $this->controller = new ConcreteAbstractController();
    }

    public function testDetermineSalutationReturnsMaleForIdGender1(): void
    {
        $customer            = new Customer();
        $customer->id_gender = 1;

        self::assertSame('m', $this->controller->callDetermineSalutation($customer));
    }

    public function testDetermineSalutationReturnsFemaleForIdGender2(): void
    {
        $customer            = new Customer();
        $customer->id_gender = 2;

        self::assertSame('w', $this->controller->callDetermineSalutation($customer));
    }

    public function testDetermineSalutationReturnsEmptyStringForIdGender0(): void
    {
        $customer            = new Customer();
        $customer->id_gender = 0;

        self::assertSame('', $this->controller->callDetermineSalutation($customer));
    }

    public function testDetermineSalutationReturnsEmptyStringForUnknownIdGender(): void
    {
        $customer            = new Customer();
        $customer->id_gender = 3;

        self::assertSame('', $this->controller->callDetermineSalutation($customer));
    }

    public function testDetermineSalutationReturnsEmptyStringForNegativeIdGender(): void
    {
        $customer            = new Customer();
        $customer->id_gender = -1;

        self::assertSame('', $this->controller->callDetermineSalutation($customer));
    }
}
