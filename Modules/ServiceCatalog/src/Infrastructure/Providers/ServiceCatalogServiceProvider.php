<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\ServiceCatalog\Application\Contracts\ServiceEligibilityResolver;
use Modules\ServiceCatalog\Application\ServiceCatalogService;

final class ServiceCatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\Modules\ServiceCatalog\Application\Contracts\CatalogResolver::class, \Modules\ServiceCatalog\Application\CurrentCatalog::class);
        $this->app->when([
            \Modules\ServiceCatalog\Application\UseCases\SaveCatalogRecord\SaveCatalogRecordHandler::class,
            \Modules\ServiceCatalog\Application\UseCases\SetCatalogRecordActive\SetCatalogRecordActiveHandler::class,
        ])->needs(\Modules\Foundation\Application\Contracts\TransactionManager::class)->give(fn() => new \Modules\Foundation\Infrastructure\Persistence\LaravelTransactionManager(attempts: 1));
        $this->app->singleton(\Modules\ServiceCatalog\Application\Repositories\CatalogRecordRepository::class, \Modules\ServiceCatalog\Infrastructure\Repositories\EloquentCatalogRecordRepository::class);
        $this->app->singleton(\Modules\ServiceCatalog\Application\Repositories\CatalogRepository::class, \Modules\ServiceCatalog\Infrastructure\Repositories\EloquentCatalogRepository::class);
        $this->app->singleton(\Modules\ServiceCatalog\Application\Repositories\CatalogCodeRepository::class, \Modules\ServiceCatalog\Infrastructure\Repositories\EloquentCatalogCodeRepository::class);
        $this->app->singleton(\Modules\ServiceCatalog\Application\Contracts\ScheduleInputValidator::class, \Modules\ServiceCatalog\Infrastructure\Adapters\LaravelScheduleInputValidator::class);
        $this->app->singleton(\Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepository::class, \Modules\ServiceCatalog\Infrastructure\Repositories\EloquentCommitmentScheduleRepository::class);
        $this->app->singleton(\Modules\ServiceCatalog\Application\Contracts\CommitmentSettings::class, \Modules\ServiceCatalog\Infrastructure\Adapters\LaravelCommitmentSettings::class);
        $this->app->singleton(\Modules\ServiceCatalog\Application\Repositories\CatalogIdentityRepository::class, \Modules\ServiceCatalog\Infrastructure\Repositories\EloquentCatalogIdentityRepository::class);
        $this->app->singleton(\Modules\ServiceCatalog\Application\Contracts\QuoteCatalogGuard::class, \Modules\ServiceCatalog\Application\Services\CurrentQuoteCatalogGuard::class);
        $this->app->singleton(\Modules\ServiceCatalog\Application\Contracts\FrozenCommitmentResolver::class, \Modules\ServiceCatalog\Application\FrozenCommitmentCompletion::class);
        $this->app->singleton(ServiceEligibilityResolver::class, ServiceCatalogService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3) . '/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
