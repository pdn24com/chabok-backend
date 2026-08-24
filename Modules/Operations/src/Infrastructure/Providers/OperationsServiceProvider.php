<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

final class OperationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $routes = dirname(__DIR__, 3).'/routes';
        $this->loadRoutesFrom($routes.'/api.php');
        $this->loadRoutesFrom($routes.'/network.php');
        $this->loadRoutesFrom($routes.'/fleet.php');
        $this->loadRoutesFrom($routes.'/runtime-pickup-routing.php');
        $this->loadRoutesFrom($routes.'/runtime-delivery.php');
    }
}
