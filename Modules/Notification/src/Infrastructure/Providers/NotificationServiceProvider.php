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
        $this->app->singleton(\Modules\Notification\Application\Contracts\DeliveryCipher::class, \Modules\Notification\Infrastructure\Gateway\LaravelDeliveryCipher::class);
        $this->app->singleton(\Modules\Notification\Application\Repositories\NotificationDeliveryRepository::class, \Modules\Notification\Infrastructure\Repositories\EloquentNotificationDeliveryRepository::class);
        $this->app->singleton(NotificationGateway::class, DeterministicNotificationGateway::class);
        $this->app->singleton(OutboxEventPublisher::class, NotificationOutboxEventPublisher::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3) . '/database/migrations');
        self::assertDeploymentAllowed((string) $this->app->environment(), (bool) config('chabok.notifications.allow_deterministic_in_staging', false));
    }

    public static function assertDeploymentAllowed(string $environment, bool $allowDeterministicInStaging): void
    {
        if ($environment === 'production') {
            throw new \LogicException('Production notification provider credentials and contracts are not approved.');
        }
        if ($environment === 'staging' && !$allowDeterministicInStaging) {
            throw new \LogicException('Staging deterministic notifications require explicit approval.');
        }
    }
}
