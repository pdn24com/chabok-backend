<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Identity\Application\Repositories\CredentialRepository;
use Modules\Identity\Application\Repositories\InvitationRepository;
use Modules\Identity\Application\Repositories\OtpChallengeRepository;
use Modules\Identity\Application\Repositories\SessionRepository;
use Modules\Identity\Infrastructure\Repositories\EloquentCredentialRepository;
use Modules\Identity\Infrastructure\Repositories\EloquentInvitationRepository;
use Modules\Identity\Infrastructure\Repositories\EloquentOtpChallengeRepository;
use Modules\Identity\Infrastructure\Repositories\EloquentSessionRepository;

final class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CredentialRepository::class, EloquentCredentialRepository::class);
        $this->app->singleton(InvitationRepository::class, EloquentInvitationRepository::class);
        $this->app->singleton(OtpChallengeRepository::class, EloquentOtpChallengeRepository::class);
        $this->app->singleton(SessionRepository::class, EloquentSessionRepository::class);
    }
}
