<?php

declare(strict_types=1);

namespace Modules\Manifest\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

final class ManifestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\Modules\Manifest\Application\Repositories\ManifestNumberRepository::class, \Modules\Manifest\Infrastructure\Repositories\EloquentManifestNumberRepository::class);
        $this->app->singleton(\Modules\Manifest\Application\Repositories\ManifestRepository::class, \Modules\Manifest\Infrastructure\Repositories\EloquentManifestRepository::class);
        $this->app->singleton(\Modules\Manifest\Application\Repositories\ManifestWorkflowRepository::class, \Modules\Manifest\Infrastructure\Repositories\EloquentManifestWorkflowRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3) . '/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
