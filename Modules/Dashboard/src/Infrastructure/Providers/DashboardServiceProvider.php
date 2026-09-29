<?php

declare(strict_types=1);

namespace Modules\Dashboard\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Dashboard\Application\Repositories\DashboardRepositoryInterface;
use Modules\Dashboard\Infrastructure\Repositories\EloquentDashboardRepository;

final class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DashboardRepositoryInterface::class, EloquentDashboardRepository::class);
    }

    public function boot(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
