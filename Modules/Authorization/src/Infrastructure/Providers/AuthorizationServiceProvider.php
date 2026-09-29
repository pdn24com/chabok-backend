<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Providers;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Modules\Authorization\Application\Contracts\AuthorizationCacheInvalidatorInterface;
use Modules\Authorization\Application\Contracts\AuthorizationGuardInterface;
use Modules\Authorization\Application\Contracts\ContextScopeResolverInterface;
use Modules\Authorization\Application\Contracts\RoleNavigationInterface;
use Modules\Authorization\Application\Contracts\RolePermissionWriterInterface;
use Modules\Authorization\Application\Contracts\RoleReaderInterface;
use Modules\Authorization\Application\Contracts\TenantAssignmentWriterInterface;
use Modules\Authorization\Application\Repositories\AssignmentRepositoryInterface;
use Modules\Authorization\Application\Repositories\PermissionRepositoryInterface;
use Modules\Authorization\Application\Repositories\RoleNavigationRepositoryInterface;
use Modules\Authorization\Application\Repositories\RolePermissionRepositoryInterface;
use Modules\Authorization\Application\Repositories\RoleRepositoryInterface;
use Modules\Authorization\Application\Repositories\TenantEntitlementRepositoryInterface;
use Modules\Authorization\Application\Services\AuthorizationCacheInvalidator;
use Modules\Authorization\Application\Services\AuthorizationGuard;
use Modules\Authorization\Application\Services\ContextScopeResolver;
use Modules\Authorization\Application\Services\RoleNavigation;
use Modules\Authorization\Application\Services\RolePermissionWriter;
use Modules\Authorization\Application\Services\RoleReader;
use Modules\Authorization\Application\Services\TenantAssignmentWriter;
use Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextHandler;
use Modules\Authorization\Infrastructure\Adapters\AccessContextAdapter;
use Modules\Authorization\Infrastructure\Adapters\AuthorizationInitialAssignmentWriter;
use Modules\Authorization\Infrastructure\Adapters\AuthorizationNodeAccessValidator;
use Modules\Authorization\Infrastructure\Adapters\AuthorizationPlatformContextValidator;
use Modules\Authorization\Infrastructure\Adapters\AuthorizationUserAdministrationAuthorizer;
use Modules\Authorization\Infrastructure\Adapters\AuthorizationUserAssignmentReader;
use Modules\Authorization\Infrastructure\Adapters\AuthorizationUserScopeAuthorizer;
use Modules\Authorization\Infrastructure\Repositories\EloquentAssignmentRepository;
use Modules\Authorization\Infrastructure\Repositories\EloquentPermissionRepository;
use Modules\Authorization\Infrastructure\Repositories\EloquentRoleNavigationRepository;
use Modules\Authorization\Infrastructure\Repositories\EloquentRolePermissionRepository;
use Modules\Authorization\Infrastructure\Repositories\EloquentRoleRepository;
use Modules\Authorization\Infrastructure\Repositories\EloquentTenantEntitlementRepository;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Application\Ports\NodeAccessValidatorInterface;
use Modules\Iam\Application\Ports\InitialAssignmentWriterInterface;
use Modules\Iam\Application\Ports\PlatformContextValidatorInterface;
use Modules\Iam\Application\Ports\UserAdministrationAuthorizerInterface;
use Modules\Iam\Application\Ports\UserAssignmentReaderInterface;
use Modules\Iam\Application\Ports\UserScopeAuthorizerInterface;

final class AuthorizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TenantEntitlementRepositoryInterface::class, EloquentTenantEntitlementRepository::class);
        $this->app->bind(RoleNavigationRepositoryInterface::class, EloquentRoleNavigationRepository::class);
        $this->app->bind(RolePermissionRepositoryInterface::class, EloquentRolePermissionRepository::class);
        $this->app->bind(AssignmentRepositoryInterface::class, EloquentAssignmentRepository::class);
        $this->app->bind(PermissionRepositoryInterface::class, EloquentPermissionRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, EloquentRoleRepository::class);
        $this->app->bind(TenantAssignmentWriterInterface::class, TenantAssignmentWriter::class);
        $this->app->bind(RoleReaderInterface::class, RoleReader::class);
        $this->app->bind(RolePermissionWriterInterface::class, RolePermissionWriter::class);
        $this->app->bind(RoleNavigationInterface::class, RoleNavigation::class);
        $this->app->bind(ContextScopeResolverInterface::class, ContextScopeResolver::class);
        $this->app->bind(AuthorizationGuardInterface::class, AuthorizationGuard::class);
        $this->app->bind(AuthorizationCacheInvalidatorInterface::class, AuthorizationCacheInvalidator::class);
        $this->app->when([ResolveContextHandler::class, AuthorizationCacheInvalidator::class])->needs(Repository::class)->give(fn () => Cache::store('redis'));
        $this->app->singleton(UserScopeAuthorizerInterface::class, AuthorizationUserScopeAuthorizer::class);
        $this->app->singleton(AccessContextResolverInterface::class, AccessContextAdapter::class);
        $this->app->singleton(NodeAccessValidatorInterface::class, AuthorizationNodeAccessValidator::class);
        $this->app->singleton(PlatformContextValidatorInterface::class, AuthorizationPlatformContextValidator::class);
        $this->app->singleton(UserAdministrationAuthorizerInterface::class, AuthorizationUserAdministrationAuthorizer::class);
        $this->app->singleton(InitialAssignmentWriterInterface::class, AuthorizationInitialAssignmentWriter::class);
        $this->app->singleton(UserAssignmentReaderInterface::class, AuthorizationUserAssignmentReader::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
