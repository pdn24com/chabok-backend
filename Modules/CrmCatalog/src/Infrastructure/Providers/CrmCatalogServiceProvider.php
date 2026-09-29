<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\CrmCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\CrmCatalog\Application\Contracts\CatalogItemValidatorInterface;
use Modules\CrmCatalog\Application\Contracts\IndustryAccessGuardInterface;
use Modules\CrmCatalog\Application\Repositories\CatalogCategoryRepositoryInterface;
use Modules\CrmCatalog\Application\Repositories\CatalogItemIndustryRepositoryInterface;
use Modules\CrmCatalog\Application\Repositories\CatalogItemRepositoryInterface;
use Modules\CrmCatalog\Application\Repositories\CatalogPersonaRepositoryInterface;
use Modules\CrmCatalog\Application\Repositories\CatalogSalesModelRepositoryInterface;
use Modules\CrmCatalog\Application\Repositories\IndustryRepositoryInterface;
use Modules\CrmCatalog\Application\Services\CatalogAccessGuard;
use Modules\CrmCatalog\Application\Services\IndustryAccessGuard;
use Modules\CrmCatalog\Application\Validators\CatalogItemValidator;
use Modules\CrmCatalog\Infrastructure\Repositories\EloquentCatalogCategoryRepository;
use Modules\CrmCatalog\Infrastructure\Repositories\EloquentCatalogItemIndustryRepository;
use Modules\CrmCatalog\Infrastructure\Repositories\EloquentCatalogItemRepository;
use Modules\CrmCatalog\Infrastructure\Repositories\EloquentCatalogPersonaRepository;
use Modules\CrmCatalog\Infrastructure\Repositories\EloquentCatalogSalesModelRepository;
use Modules\CrmCatalog\Infrastructure\Repositories\EloquentIndustryRepository;

final class CrmCatalogServiceProvider extends ServiceProvider
{
    /** One repository per table, so a use case injects only the tables it actually touches. */
    private const REPOSITORIES = [
        IndustryRepositoryInterface::class => EloquentIndustryRepository::class,
        CatalogItemRepositoryInterface::class => EloquentCatalogItemRepository::class,
        CatalogCategoryRepositoryInterface::class => EloquentCatalogCategoryRepository::class,
        CatalogPersonaRepositoryInterface::class => EloquentCatalogPersonaRepository::class,
        CatalogSalesModelRepositoryInterface::class => EloquentCatalogSalesModelRepository::class,
        CatalogItemIndustryRepositoryInterface::class => EloquentCatalogItemIndustryRepository::class,
    ];

    public function register(): void
    {
        foreach (self::REPOSITORIES as $contract => $implementation) {
            $this->app->bind($contract, $implementation);
        }
        $this->app->bind(IndustryAccessGuardInterface::class, IndustryAccessGuard::class);
        $this->app->bind(CatalogAccessGuardInterface::class, CatalogAccessGuard::class);
        $this->app->bind(CatalogItemValidatorInterface::class, CatalogItemValidator::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
