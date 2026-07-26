<?php

declare(strict_types=1);

namespace Modules\Notification\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Foundation\Application\Contracts\OutboxEventPublisher;
use Modules\Notification\Application\Contracts\NotificationGateway;
use Modules\Notification\Infrastructure\Gateway\DeterministicNotificationGateway;
use Modules\Notification\Infrastructure\Publishing\NotificationOutboxEventPublisher;

final class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationGateway::class, DeterministicNotificationGateway::class);
        $this->app->singleton(OutboxEventPublisher::class, NotificationOutboxEventPublisher::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');

        if ($this->app->environment(['staging', 'production'])) {
            throw new \LogicException(
                'Staging and production notification provider credentials and contracts are not approved.',
            );
        }
    }
}
