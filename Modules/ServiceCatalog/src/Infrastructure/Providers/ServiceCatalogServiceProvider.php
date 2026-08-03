<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\ServiceCatalog\Application\Contracts\ServiceEligibilityResolver;
use Modules\ServiceCatalog\Application\ServiceCatalogService;

final class ServiceCatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ServiceEligibilityResolver::class, ServiceCatalogService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->loadRoutesFrom(dirname(__DIR__, 3).'/routes/api.php');
    }
}
