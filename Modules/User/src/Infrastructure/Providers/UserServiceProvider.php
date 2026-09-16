<?php

declare(strict_types=1);

namespace Modules\User\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\User\Application\Contracts\InitialAssignmentWriter;
use Modules\User\Application\Contracts\UserAdministrationAuthorizer;
use Modules\User\Application\Contracts\UserAssignmentReader;
use Modules\User\Infrastructure\Authorization\DenyUserAdministrationAuthorizer;
use Modules\User\Infrastructure\Authorization\UnavailableInitialAssignmentWriter;
use Modules\User\Infrastructure\Authorization\UnavailableUserAssignmentReader;

final class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(RepositoryServiceProvider::class);
        $this->app->singleton(UserAdministrationAuthorizer::class, DenyUserAdministrationAuthorizer::class);
        $this->app->singleton(InitialAssignmentWriter::class, UnavailableInitialAssignmentWriter::class);
        $this->app->singleton(UserAssignmentReader::class, UnavailableUserAssignmentReader::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3) . '/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
