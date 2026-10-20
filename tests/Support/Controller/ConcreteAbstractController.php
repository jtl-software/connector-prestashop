<?php

declare(strict_types=1);

namespace Tests\Support\Controller;

use Customer;
use DateTimeInterface;
use Db;
use jtl\Connector\Presta\Controller\AbstractController;
use Psr\Log\NullLogger;

/**
 * Concrete testable subclass that overrides the constructor to avoid
 * Db::getInstance() and ReflectionClass usage in the production constructor.
 */
final class ConcreteAbstractController extends AbstractController
{
    public function __construct()
    {
        $this->db             = new Db();
        $this->logger         = new NullLogger();
        $this->controllerName = 'TestController';
        // mapper is not required for the methods under test
    }

    public function callCreateDateTime(?string $date): ?DateTimeInterface
    {
        return $this->createDateTime($date);
    }

    public function callDetermineSalutation(Customer $customer): string
    {
        return $this->determineSalutation($customer);
    }
}
