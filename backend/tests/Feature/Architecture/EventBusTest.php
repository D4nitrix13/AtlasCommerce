<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Core\Shared\Events\Event;
use Core\Shared\Events\EventBus;
use Illuminate\Support\Facades\Event as EventFacade;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TestEvent implements Event
{
    public function __construct(
        public readonly string $name,
    ) {}
}

final class TestEventListener
{
    public static int $calls = 0;

    public function __invoke(TestEvent $event): void
    {
        self::$calls++;
    }
}

#[Group('architecture')]
#[Group('events')]
final class EventBusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        EventFacade::forget(TestEvent::class);
        TestEventListener::$calls = 0;
    }

    protected function tearDown(): void
    {
        EventFacade::forget(TestEvent::class);

        parent::tearDown();
    }

    #[Test]
    public function dispatch_delivers_events_synchronously(): void
    {
        $received = null;
        $eventBus = app(EventBus::class);
        $eventBus->listen(
            eventClass: TestEvent::class,
            listener: function (TestEvent $event) use (&$received): void {
                $received = $event;
            },
        );
        $event = new TestEvent(name: 'order-created');

        $eventBus->dispatch(event: $event);

        self::assertSame($event, $received);
    }

    #[Test]
    public function dispatch_resolves_listener_classes(): void
    {
        $eventBus = app(EventBus::class);
        $eventBus->listen(
            eventClass: TestEvent::class,
            listener: TestEventListener::class,
        );

        $eventBus->dispatch(event: new TestEvent(name: 'payment-captured'));

        self::assertSame(1, TestEventListener::$calls);
    }

    #[Test]
    public function event_bus_is_registered_as_a_singleton(): void
    {
        self::assertSame(
            app(EventBus::class),
            app(EventBus::class),
        );
    }
}
