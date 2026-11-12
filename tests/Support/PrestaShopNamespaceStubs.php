<?php

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Core\Foundation\IoC;

if (!class_exists(\PrestaShop\PrestaShop\Core\Foundation\IoC\Exception::class)) {
    class Exception extends \RuntimeException
    {
    }
}
