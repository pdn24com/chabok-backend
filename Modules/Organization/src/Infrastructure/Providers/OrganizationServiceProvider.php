<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Foundation\Application\Ports\ScopeTopologyInterface;
use Modules\Organization\Application\Contracts\HierarchyEditorInterface;
use Modules\Organization\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Organization\Application\Contracts\NetworkChangeRecorderInterface;
use Modules\Organization\Application\Contracts\NetworkInputValidatorInterface;
use Modules\Organization\Application\Repositories\AreaHierarchyRepositoryInterface;
use Modules\Organization\Application\Repositories\AreaRepositoryInterface;
use Modules\Organization\Application\Repositories\NodeRepositoryInterface;
use Modules\Organization\Application\Repositories\TenantRepositoryInterface;
use Modules\Organization\Application\Services\HierarchyEditor;
use Modules\Organization\Application\Services\NetworkAccessGuard;
use Modules\Organization\Application\Services\NetworkChangeRecorder;
use Modules\Organization\Application\Services\NetworkInputValidator;
use Modules\Organization\Infrastructure\Adapters\EloquentScopeTopology;
use Modules\Organization\Infrastructure\Repositories\EloquentAreaHierarchyRepository;
use Modules\Organization\Infrastructure\Repositories\EloquentAreaRepository;
use Modules\Organization\Infrastructure\Repositories\EloquentNodeRepository;
use Modules\Organization\Infrastructure\Repositories\EloquentTenantRepository;

final class OrganizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TenantRepositoryInterface::class, EloquentTenantRepository::class);
        $this->app->bind(AreaHierarchyRepositoryInterface::class, EloquentAreaHierarchyRepository::class);
        $this->app->bind(AreaRepositoryInterface::class, EloquentAreaRepository::class);
        $this->app->bind(NodeRepositoryInterface::class, EloquentNodeRepository::class);
        $this->app->bind(NetworkInputValidatorInterface::class, NetworkInputValidator::class);
        $this->app->bind(HierarchyEditorInterface::class, HierarchyEditor::class);
        $this->app->bind(NetworkAccessGuardInterface::class, NetworkAccessGuard::class);
        $this->app->bind(NetworkChangeRecorderInterface::class, NetworkChangeRecorder::class);
        $this->app->singleton(ScopeTopologyInterface::class, EloquentScopeTopology::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
