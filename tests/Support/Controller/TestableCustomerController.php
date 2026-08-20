<?php

declare(strict_types=1);

namespace Tests\Support\Controller;

use Address;
use Customer;
use Db;
use Jtl\Connector\Core\Model\Customer as JtlCustomer;
use jtl\Connector\Presta\Controller\CustomerController;
use Psr\Log\NullLogger;

final class TestableCustomerController extends CustomerController
{
    public function __construct()
    {
        $this->db             = new Db();
        $this->logger         = new NullLogger();
        $this->controllerName = 'CustomerController';
    }

    protected function getJtlLanguageIsoFromLanguageId(string|int $langId): string
    {
        return 'eng';
    }

    protected function getPrestaCountryIdFromIso(string $iso): ?int
    {
        return match ($iso) {
            'DE'    => 8,
            'FR'    => 9,
            default => null,
        };
    }

    protected function getPrestaLanguageIdFromIso(string $iso): int
    {
        return 1;
    }

    protected function getPrestaContextShopId(): int
    {
        return 1;
    }

    protected function determineSalutation(Customer $customer): string
    {
        return 'm';
    }

    public function exposeCreatePrestaAddress(JtlCustomer $jtl, Address $addr, Customer $presta): Address
    {
        return $this->createPrestaAddress($jtl, $addr, $presta);
    }

    public function exposeChangeCustomerGroup(JtlCustomer $jtl, Customer $presta, bool $isNew): void
    {
        $this->changeCustomerGroup($jtl, $presta, $isNew);
    }

    public function injectDb(Db $db): void
    {
        $this->db = $db;
    }

    public function exposeCreateJtlCustomer(array $data): JtlCustomer
    {
        return $this->createJtlCustomer($data);
    }

    public function exposeCreatePrestaCustomer(
        JtlCustomer $jtl,
        Customer $presta
    ): Customer {
        return $this->createPrestaCustomer($jtl, $presta);
    }
}
