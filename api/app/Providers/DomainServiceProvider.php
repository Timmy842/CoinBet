<?php

declare(strict_types=1);

namespace App\Providers;

use CoinBet\Shared\Domain\Event\DomainEventPublisher;
use CoinBet\Shared\Infrastructure\Event\LaravelDomainEventPublisher;
use Illuminate\Support\ServiceProvider;

final class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DomainEventPublisher::class, LaravelDomainEventPublisher::class);
    }
}
