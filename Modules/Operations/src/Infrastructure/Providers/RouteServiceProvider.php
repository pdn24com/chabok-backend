<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

final class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(dirname(__DIR__, 2) . '/Presentation/Routes/api.php');
        $this->loadRoutesFrom(dirname(__DIR__, 2) . '/Presentation/Routes/network.php');
        $this->loadRoutesFrom(dirname(__DIR__, 2) . '/Presentation/Routes/fleet.php');
        $this->loadRoutesFrom(dirname(__DIR__, 2) . '/Presentation/Routes/runtime-pickup-routing.php');
        $this->loadRoutesFrom(dirname(__DIR__, 2) . '/Presentation/Routes/runtime-delivery.php');
    }
}
