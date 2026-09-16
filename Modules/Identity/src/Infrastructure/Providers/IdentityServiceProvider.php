<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Foundation\Application\Contracts\AccessSessionValidator;
use Modules\Identity\Application\IdentityService;
use Modules\Identity\Application\Contracts\PlatformContextValidator;
use Modules\Identity\Infrastructure\Authorization\UnavailablePlatformContextValidator;
use Modules\Identity\Infrastructure\Security\DatabaseAccessSessionValidator;
use Modules\User\Application\Contracts\IdentityProvisioner;
use Modules\User\Application\Contracts\UserSessionManager;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(RepositoryServiceProvider::class);
        $this->app->singleton(\Modules\Identity\Application\Contracts\SessionRegistry::class, \Modules\Identity\Infrastructure\Security\RedisSessionRegistry::class);
        $this->app->singleton(\Modules\Identity\Application\Contracts\DeliveryCipher::class, \Modules\Identity\Infrastructure\Security\LaravelDeliveryCipher::class);
        $this->app->singleton(\Modules\Identity\Application\Contracts\SessionSettings::class, \Modules\Identity\Infrastructure\Security\LaravelSessionSettings::class);
        $this->app->singleton(\Modules\User\Application\Contracts\UserInvitationReader::class, \Modules\Identity\Infrastructure\Persistence\EloquentUserInvitationReader::class);
        $this->app->singleton(IdentityService::class);
        $this->app->alias(IdentityService::class, IdentityProvisioner::class);
        $this->app->alias(IdentityService::class, UserSessionManager::class);
        $this->app->singleton(AccessSessionValidator::class, DatabaseAccessSessionValidator::class);
        $this->app->singleton(PlatformContextValidator::class, UnavailablePlatformContextValidator::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3) . '/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
