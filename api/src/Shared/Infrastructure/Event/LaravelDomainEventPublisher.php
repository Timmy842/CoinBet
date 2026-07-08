<?php

declare(strict_types=1);

namespace CoinBet\Shared\Infrastructure\Event;

use CoinBet\Shared\Domain\Event\DomainEventPublisher;
use Illuminate\Contracts\Events\Dispatcher;

final class LaravelDomainEventPublisher implements DomainEventPublisher
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
    ) {}

    public function publish(object $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
