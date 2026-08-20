<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\SpecificController;

use Feature;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\Specific;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableSpecificController;

final class DeleteTest extends TestCase
{
    private TestableSpecificController $controller;

    protected function setUp(): void
    {
        Feature::resetMock();
        $this->controller = new TestableSpecificController();
    }

    public function testDeleteReturnsModel(): void
    {
        $jtlSpecific = (new Specific())
            ->setId(new Identity('7'));

        $result = $this->controller->delete($jtlSpecific);

        self::assertSame($jtlSpecific, $result);
    }
}
