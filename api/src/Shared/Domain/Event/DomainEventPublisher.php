<?php

declare(strict_types=1);

namespace CoinBet\Shared\Domain\Event;

interface DomainEventPublisher
{
    public function publish(object $event): void;
}
