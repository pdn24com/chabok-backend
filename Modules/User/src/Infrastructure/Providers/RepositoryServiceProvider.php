<?php

declare(strict_types=1);

namespace Modules\User\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\User\Application\Repositories\UserRepository;
use Modules\User\Infrastructure\Repositories\EloquentUserRepository;

final class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UserRepository::class, EloquentUserRepository::class);
    }
}
