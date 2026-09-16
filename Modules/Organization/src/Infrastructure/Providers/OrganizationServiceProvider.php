<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

final class OrganizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\Modules\Organization\Application\Repositories\NetworkRepository::class, \Modules\Organization\Infrastructure\Repositories\EloquentNetworkRepository::class);
        $this->app->singleton(\Modules\Foundation\Application\Contracts\ScopeTopology::class, \Modules\Organization\Infrastructure\Adapters\EloquentScopeTopology::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3) . '/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
