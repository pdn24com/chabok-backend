<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\CrmTask\Application\Ports\TeamDirectoryInterface;
use Modules\CrmTeam\Application\Contracts\TeamAccessGuardInterface;
use Modules\CrmTeam\Application\Repositories\MembershipEventRepositoryInterface;
use Modules\CrmTeam\Application\Repositories\TeamMemberRepositoryInterface;
use Modules\CrmTeam\Application\Repositories\TeamRepositoryInterface;
use Modules\CrmTeam\Application\Services\TeamAccessGuard;
use Modules\CrmTeam\Infrastructure\Adapters\TaskTeamDirectory;
use Modules\CrmTeam\Infrastructure\Repositories\EloquentMembershipEventRepository;
use Modules\CrmTeam\Infrastructure\Repositories\EloquentTeamMemberRepository;
use Modules\CrmTeam\Infrastructure\Repositories\EloquentTeamRepository;

final class CrmTeamServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TeamRepositoryInterface::class, EloquentTeamRepository::class);
        $this->app->bind(TeamMemberRepositoryInterface::class, EloquentTeamMemberRepository::class);
        $this->app->bind(MembershipEventRepositoryInterface::class, EloquentMembershipEventRepository::class);
        $this->app->bind(TeamAccessGuardInterface::class, TeamAccessGuard::class);
        $this->app->bind(TeamDirectoryInterface::class, TaskTeamDirectory::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
