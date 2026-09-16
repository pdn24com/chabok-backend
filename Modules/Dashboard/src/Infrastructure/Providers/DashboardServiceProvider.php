<?php

declare(strict_types=1);

namespace Modules\Dashboard\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

final class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\Modules\Dashboard\Application\Repositories\DashboardRepository::class, \Modules\Dashboard\Infrastructure\Repositories\SqlDashboardRepository::class);
    }

    public function boot(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
