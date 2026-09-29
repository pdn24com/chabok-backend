<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Adapters\OperationalProfileAdapter;
use Illuminate\Support\ServiceProvider;
use Modules\Iam\Application\Ports\OperationalProfileWriterInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OperationalProfileWriterInterface::class, OperationalProfileAdapter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
