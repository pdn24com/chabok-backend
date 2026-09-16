<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Authorization\Application\AuthorizationService;
use Modules\Authorization\Infrastructure\Adapters\AuthorizationInitialAssignmentWriter;
use Modules\Authorization\Infrastructure\Adapters\AuthorizationNodeAccessValidator;
use Modules\Authorization\Infrastructure\Adapters\AuthorizationPlatformContextValidator;
use Modules\Authorization\Infrastructure\Adapters\AuthorizationUserAdministrationAuthorizer;
use Modules\Authorization\Infrastructure\Adapters\AuthorizationUserAssignmentReader;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Application\Contracts\NodeAccessValidator;
use Modules\Identity\Application\Contracts\PlatformContextValidator;
use Modules\User\Application\Contracts\InitialAssignmentWriter;
use Modules\User\Application\Contracts\UserAdministrationAuthorizer;
use Modules\User\Application\Contracts\UserAssignmentReader;

final class AuthorizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuthorizationService::class);
        $this->app->singleton(\Modules\User\Application\Contracts\UserScopeAuthorizer::class, \Modules\Authorization\Infrastructure\Adapters\AuthorizationUserScopeAuthorizer::class);
        $this->app->alias(AuthorizationService::class, AuthorizationContextResolver::class);
        $this->app->singleton(NodeAccessValidator::class, AuthorizationNodeAccessValidator::class);
        $this->app->singleton(PlatformContextValidator::class, AuthorizationPlatformContextValidator::class);
        $this->app->singleton(UserAdministrationAuthorizer::class, AuthorizationUserAdministrationAuthorizer::class);
        $this->app->singleton(InitialAssignmentWriter::class, AuthorizationInitialAssignmentWriter::class);
        $this->app->singleton(UserAssignmentReader::class, AuthorizationUserAssignmentReader::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->loadRoutesFrom(dirname(__DIR__, 3).'/routes/api.php');
    }
}
