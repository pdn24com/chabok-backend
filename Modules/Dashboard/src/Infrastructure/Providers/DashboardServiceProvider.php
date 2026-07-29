<?php

declare(strict_types=1);

namespace Modules\Dashboard\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

final class DashboardServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(dirname(__DIR__, 3).'/routes/api.php');
    }
}
