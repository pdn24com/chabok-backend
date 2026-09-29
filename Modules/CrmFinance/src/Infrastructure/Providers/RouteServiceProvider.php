<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

final class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(dirname(__DIR__, 2).'/Presentation/Routes/api.php');
    }
}
