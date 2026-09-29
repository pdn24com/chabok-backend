<?php

declare(strict_types=1);

namespace Modules\CrmSales\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\CrmSales\Application\Contracts\SalesDocumentAccessGuardInterface;
use Modules\CrmSales\Application\Repositories\SalesDocumentRepositoryInterface;
use Modules\CrmSales\Application\Repositories\SalesDocumentVersionRepositoryInterface;
use Modules\CrmSales\Application\Services\SalesDocumentAccessGuard;
use Modules\CrmSales\Infrastructure\Repositories\EloquentSalesDocumentRepository;
use Modules\CrmSales\Infrastructure\Repositories\EloquentSalesDocumentVersionRepository;

final class CrmSalesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SalesDocumentRepositoryInterface::class, EloquentSalesDocumentRepository::class);
        $this->app->bind(SalesDocumentVersionRepositoryInterface::class, EloquentSalesDocumentVersionRepository::class);
        $this->app->bind(SalesDocumentAccessGuardInterface::class, SalesDocumentAccessGuard::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
