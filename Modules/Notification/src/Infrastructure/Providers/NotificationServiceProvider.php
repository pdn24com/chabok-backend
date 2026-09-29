<?php

declare(strict_types=1);

namespace Modules\Notification\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use LogicException;
use Modules\Foundation\Application\Ports\OutboxEventPublisherInterface;
use Modules\Notification\Application\Contracts\DeliveryCipherInterface;
use Modules\Notification\Application\Contracts\NotificationGatewayInterface;
use Modules\Notification\Application\Repositories\NotificationDeliveryRepositoryInterface;
use Modules\Notification\Infrastructure\Gateway\DeterministicNotificationGateway;
use Modules\Notification\Infrastructure\Gateway\LaravelDeliveryCipher;
use Modules\Notification\Infrastructure\Publishing\NotificationOutboxEventPublisher;
use Modules\Notification\Infrastructure\Repositories\EloquentNotificationDeliveryRepository;

final class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificationDeliveryRepositoryInterface::class, EloquentNotificationDeliveryRepository::class);
        $this->app->singleton(DeliveryCipherInterface::class, LaravelDeliveryCipher::class);
        $this->app->singleton(NotificationGatewayInterface::class, DeterministicNotificationGateway::class);
        $this->app->singleton(OutboxEventPublisherInterface::class, NotificationOutboxEventPublisher::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        self::assertDeploymentAllowed((string) $this->app->environment(), (bool) config('chabok.notifications.allow_deterministic_in_staging', false));
    }

    public static function assertDeploymentAllowed(string $environment, bool $allowDeterministicInStaging): void
    {
        if ($environment === 'production') {
            throw new LogicException('Production notification provider credentials and contracts are not approved.');
        }
        if ($environment === 'staging' && ! $allowDeterministicInStaging) {
            throw new LogicException('Staging deterministic notifications require explicit approval.');
        }
    }
}
