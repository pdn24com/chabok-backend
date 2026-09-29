<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\CrmOpportunitie\Application\Contracts\OpportunityAccessGuardInterface;
use Modules\CrmOpportunitie\Application\Repositories\FunnelStepRepositoryInterface;
use Modules\CrmOpportunitie\Application\Repositories\OpportunityEventRepositoryInterface;
use Modules\CrmOpportunitie\Application\Repositories\OpportunityRepositoryInterface;
use Modules\CrmOpportunitie\Application\Repositories\SalesFunnelRepositoryInterface;
use Modules\CrmOpportunitie\Application\Services\OpportunityAccessGuard;
use Modules\CrmOpportunitie\Infrastructure\Adapters\TaskOpportunityDirectory;
use Modules\CrmOpportunitie\Infrastructure\Repositories\EloquentFunnelStepRepository;
use Modules\CrmOpportunitie\Infrastructure\Repositories\EloquentOpportunityEventRepository;
use Modules\CrmOpportunitie\Infrastructure\Repositories\EloquentOpportunityRepository;
use Modules\CrmOpportunitie\Infrastructure\Repositories\EloquentSalesFunnelRepository;
use Modules\CrmTask\Application\Ports\OpportunityDirectoryInterface;

final class CrmOpportunitieServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OpportunityRepositoryInterface::class, EloquentOpportunityRepository::class);
        $this->app->bind(SalesFunnelRepositoryInterface::class, EloquentSalesFunnelRepository::class);
        $this->app->bind(FunnelStepRepositoryInterface::class, EloquentFunnelStepRepository::class);
        $this->app->bind(OpportunityEventRepositoryInterface::class, EloquentOpportunityEventRepository::class);
        $this->app->bind(OpportunityAccessGuardInterface::class, OpportunityAccessGuard::class);
        $this->app->bind(OpportunityDirectoryInterface::class, TaskOpportunityDirectory::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
