<?php

declare(strict_types=1);

namespace Core\Shared\Events;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;

final class InProcessEventBus implements EventBus
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
    ) {}

    public function dispatch(Event $event): void
    {
        $this->dispatcher->dispatch(event: $event);
    }

    public function listen(string $eventClass, Closure|string $listener): void
    {
        $this->dispatcher->listen(events: $eventClass, listener: $listener);
    }
}
