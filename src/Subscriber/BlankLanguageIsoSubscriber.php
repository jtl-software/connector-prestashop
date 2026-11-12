<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Subscriber;

use Jtl\Connector\Core\Definition\Event;
use Jtl\Connector\Core\Event\RpcEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * jtl/connector converts the "languageISO" (ISO 639-2) of every model it deserializes and throws for values it does
 * not know. It skips empty values, but not whitespace-only ones, so those are emptied before deserialization.
 */
class BlankLanguageIsoSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Event::createRpcEventName(Event::BEFORE) => 'emptyBlankLanguageIso',
        ];
    }

    /**
     * @param RpcEvent $event
     *
     * @return void
     */
    public function emptyBlankLanguageIso(RpcEvent $event): void
    {
        $data = $event->getData();
        if (!\is_array($data)) {
            return;
        }

        \array_walk_recursive($data, static function (mixed &$value, int|string $key): void {
            if ($key === 'languageISO' && \is_string($value) && \trim($value) === '') {
                $value = '';
            }
        });
        $event->setData($data);
    }
}
