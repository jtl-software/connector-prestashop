<?php

declare(strict_types=1);

namespace Tests\Support\Controller;

use jtl\Connector\Presta\Controller\AbstractController;

/**
 * Minimal concrete subclass that does NOT override __construct(), so the real
 * AbstractController::__construct() is exercised directly.
 */
final class ConcreteAbstractControllerWithRealConstructor extends AbstractController
{
}
