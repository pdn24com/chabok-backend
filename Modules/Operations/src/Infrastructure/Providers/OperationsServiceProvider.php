<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

final class OperationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\Modules\Operations\Application\Contracts\ManifestExceptionAccess::class, \Modules\Operations\Infrastructure\Repositories\EloquentManifestExceptionAccess::class);
        $this->app->singleton(\Modules\Operations\Application\Contracts\ManifestDirectoryReader::class, \Modules\Operations\Infrastructure\Repositories\EloquentManifestDirectoryReader::class);
        $this->app->singleton(\Modules\Operations\Application\Contracts\ManifestRouteAccess::class, \Modules\Operations\Infrastructure\Repositories\EloquentManifestRouteAccess::class);
        $this->app->singleton(\Modules\Operations\Application\Contracts\ManifestTaskAccess::class, \Modules\Operations\Infrastructure\Repositories\EloquentManifestTaskAccess::class);
        $this->app->singleton(\Modules\Operations\Application\Repositories\MovementRepository::class, \Modules\Operations\Infrastructure\Repositories\EloquentMovementRepository::class);
        $this->app->singleton(\Modules\Operations\Application\Repositories\CoveragePolicyRepository::class, \Modules\Operations\Infrastructure\Repositories\EloquentCoveragePolicyRepository::class);
        $this->app->singleton(\Modules\Operations\Application\Repositories\RouteDefinitionRepository::class, \Modules\Operations\Infrastructure\Repositories\EloquentRouteDefinitionRepository::class);
        $this->app->singleton(\Modules\Operations\Application\Repositories\FleetRepository::class, \Modules\Operations\Infrastructure\Repositories\EloquentFleetRepository::class);
        $this->app->singleton(\Modules\Operations\Application\Repositories\OperationalDirectoryRepository::class, \Modules\Operations\Infrastructure\Repositories\EloquentOperationalDirectoryRepository::class);
        $this->app->when(\Modules\Operations\Application\UseCases\CreateOperationalRoute\CreateOperationalRouteHandler::class)->needs(\Modules\Foundation\Application\Contracts\TransactionManager::class)->give(fn() => new \Modules\Foundation\Infrastructure\Persistence\LaravelTransactionManager(1));
        $this->app->singleton(\Modules\Operations\Application\Repositories\DeliveryTaskRepository::class, \Modules\Operations\Infrastructure\Repositories\EloquentDeliveryTaskRepository::class);
        $this->app->singleton(\Modules\Operations\Application\Repositories\PickupTaskRepository::class, \Modules\Operations\Infrastructure\Repositories\EloquentPickupTaskRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3) . '/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
