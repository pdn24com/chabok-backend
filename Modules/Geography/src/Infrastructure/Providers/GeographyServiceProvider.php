<?php

declare(strict_types=1);

namespace Modules\Geography\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

final class GeographyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->loadRoutesFrom(dirname(__DIR__, 3).'/routes/api.php');
    }
}
