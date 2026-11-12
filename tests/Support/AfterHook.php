<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * Stand-ins for modules hooked into PrestaShop's actionObject<Class>(Add|Update|Delete)After hooks, which PrestaShop
 * runs after the write. Assign one to \ObjectModel::$mockAfterHook; \ObjectModel::resetObjectModelMock() removes it.
 */
final class AfterHook
{
    /**
     * Behaves like ps_eventbus 4.1.1 on a shop that isn't linked to a PrestaShop account: its error handler reads the
     * file of every stack frame and throws a TypeError for a frame without one, i.e. a callback that an internal PHP
     * function called. The frames up to the test method are checked, as only they exist in a real request; that
     * includes callbacks defined in the test.
     *
     * @return \Closure(\ObjectModel|null, string): void
     */
    public static function psEventbusErrorHandler(): \Closure
    {
        return static function (): void {
            foreach ((new \Exception())->getTrace() as $frame) {
                if (!isset($frame['file'])) {
                    throw new \TypeError(
                        'PrestaShop\Module\PsEventbus\Handler\ErrorHandler\ErrorHandler::getCodeContext(): Argument #1'
                        . ' ($file) must be of type string, null given, frame ' . $frame['function']
                    );
                }
                if (
                    isset($frame['class'])
                    && \is_subclass_of($frame['class'], TestCase::class)
                    && !\str_contains($frame['function'], '{closure')
                ) {
                    return; // the test method; PHPUnit's own frames follow
                }
            }
        };
    }

    /**
     * Throws $error after every write of the given event ('Add', 'Update' or 'Delete').
     *
     * @return \Closure(\ObjectModel|null, string): void
     */
    public static function throwing(\Throwable $error, string $event = 'Add'): \Closure
    {
        return static function (?\ObjectModel $object, string $hookEvent) use ($error, $event): void {
            if ($hookEvent === $event) {
                throw $error;
            }
        };
    }
}
