<?php

declare(strict_types=1);

namespace Tests\Unit\Subscriber;

use Jtl\Connector\Core\Definition\Action;
use Jtl\Connector\Core\Definition\Controller;
use Jtl\Connector\Core\Definition\Event;
use Jtl\Connector\Core\Event\RpcEvent;
use Jtl\Connector\Core\Model\Category;
use Jtl\Connector\Core\Serializer\SerializerBuilder;
use jtl\Connector\Presta\Subscriber\BlankLanguageIsoSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class BlankLanguageIsoSubscriberTest extends TestCase
{
    /**
     * @return array<int, array<string, mixed>>
     */
    private function categoryPush(string ...$languageIsos): array
    {
        $i18ns = [];
        foreach ($languageIsos as $index => $languageIso) {
            $i18ns[] = ['languageISO' => $languageIso, 'name' => 'Category ' . $index];
        }

        return [['id' => ['', 1], 'i18ns' => $i18ns]];
    }

    private function dispatch(mixed $data): mixed
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new BlankLanguageIsoSubscriber());
        $event = new RpcEvent($data, Controller::CATEGORY, Action::PUSH);
        $dispatcher->dispatch($event, Event::createRpcEventName(Event::BEFORE));

        return $event->getData();
    }

    public function testWhitespaceOnlyLanguageIsoIsEmptiedAtAnyDepth(): void
    {
        $data = $this->dispatch($this->categoryPush(' ', "\t\n", 'ger', ''));

        self::assertSame(['', '', 'ger', ''], \array_column($data[0]['i18ns'], 'languageISO'));
    }

    public function testOtherKeysAndNonStringValuesAreLeftAlone(): void
    {
        $data = [['name' => ' ', 'languageISO' => null, 'i18ns' => [['languageIso' => ' ']]]];

        self::assertSame($data, $this->dispatch($data));
    }

    public function testNonArrayDataIsLeftAlone(): void
    {
        self::assertSame('foo', $this->dispatch('foo'));
    }

    /**
     * jtl/connector 6 skips empty values itself (this used to be patches/jtl-connector-language-iso-subscriber-empty-
     * string.patch), together with this subscriber blank values no longer break the deserialization of a push.
     */
    public function testBlankLanguageIsoNoLongerBreaksDeserialization(): void
    {
        $serializer = SerializerBuilder::create()->build();

        /** @var array<int, Category> $categories */
        $categories = $serializer->fromArray($this->dispatch($this->categoryPush('', ' ', 'ger')), 'array<' . Category::class . '>');

        self::assertSame(['', '', 'de'], \array_map(
            static fn($i18n): string => $i18n->getLanguageIso(),
            $categories[0]->getI18ns()
        ));
    }
}
