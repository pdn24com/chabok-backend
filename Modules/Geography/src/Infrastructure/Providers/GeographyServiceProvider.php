<?php

declare(strict_types=1);

namespace Modules\Geography\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Foundation\Application\Contracts\CanonicalGeographyResolver;
use Modules\Geography\Application\GeographyResolver;

final class GeographyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CanonicalGeographyResolver::class, GeographyResolver::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->loadRoutesFrom(dirname(__DIR__, 3).'/routes/api.php');
    }
}
