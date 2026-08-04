<?php

declare(strict_types=1);

namespace Core\Shared\Events;

use Closure;

interface EventBus
{
    public function dispatch(Event $event): void;

    public function listen(string $eventClass, Closure|string $listener): void;
}
