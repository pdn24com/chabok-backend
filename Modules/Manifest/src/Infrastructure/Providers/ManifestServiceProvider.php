<?php

declare(strict_types=1);

namespace Modules\Manifest\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

final class ManifestServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->loadRoutesFrom(dirname(__DIR__, 3).'/routes/api.php');
    }
}
