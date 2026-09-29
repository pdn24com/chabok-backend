<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\CrmCatalog\Application\Contracts\IndustryAccessGuardInterface;
use Modules\CrmCatalog\Application\Repositories\IndustryRepositoryInterface;
use Modules\CrmCatalog\Application\Services\IndustryAccessGuard;
use Modules\CrmCatalog\Infrastructure\Repositories\EloquentIndustryRepository;

final class CrmCatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IndustryRepositoryInterface::class, EloquentIndustryRepository::class);
        $this->app->bind(IndustryAccessGuardInterface::class, IndustryAccessGuard::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
