<?php

declare(strict_types=1);

namespace Modules\Iam\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Foundation\Application\Ports\AccessSessionValidatorInterface;
use Modules\Foundation\Application\Ports\CommandActorLockInterface;
use Modules\Iam\Application\Contracts\CredentialWriterInterface;
use Modules\Iam\Application\Contracts\DeliveryCipherInterface;
use Modules\Iam\Application\Contracts\IdentifierNormalizerInterface;
use Modules\Iam\Application\Contracts\SessionIssuerInterface;
use Modules\Iam\Application\Contracts\SessionLifecycleInterface;
use Modules\Iam\Application\Contracts\SessionRegistryInterface;
use Modules\Iam\Application\Contracts\SessionSettingsInterface;
use Modules\Iam\Application\Contracts\UserAccessGuardInterface;
use Modules\Iam\Application\Contracts\UserIdentifierResolverInterface;
use Modules\Iam\Application\Contracts\VerificationProofConsumerInterface;
use Modules\Iam\Application\Repositories\CredentialRepositoryInterface;
use Modules\Iam\Application\Repositories\InvitationRepositoryInterface;
use Modules\Iam\Application\Repositories\OtpChallengeRepositoryInterface;
use Modules\Iam\Application\Repositories\SessionRepositoryInterface;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;
use Modules\Iam\Application\Services\CredentialWriter;
use Modules\Iam\Application\Services\IdentifierNormalizer;
use Modules\Iam\Application\Services\SessionIssuer;
use Modules\Iam\Application\Services\SessionLifecycle;
use Modules\Iam\Application\Services\UserAccessGuard;
use Modules\Iam\Application\Services\UserIdentifierResolver;
use Modules\Iam\Application\Services\VerificationProofConsumer;
use Modules\Iam\Infrastructure\Adapters\UserCommandActorLock;
use Modules\Iam\Infrastructure\Repositories\EloquentCredentialRepository;
use Modules\Iam\Infrastructure\Repositories\EloquentInvitationRepository;
use Modules\Iam\Infrastructure\Repositories\EloquentOtpChallengeRepository;
use Modules\Iam\Infrastructure\Repositories\EloquentSessionRepository;
use Modules\Iam\Infrastructure\Repositories\EloquentUserRepository;
use Modules\Iam\Infrastructure\Security\DatabaseAccessSessionValidator;
use Modules\Iam\Infrastructure\Security\LaravelDeliveryCipher;
use Modules\Iam\Infrastructure\Security\LaravelSessionSettings;
use Modules\Iam\Infrastructure\Security\RedisSessionRegistry;

final class IamServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Account
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(UserIdentifierResolverInterface::class, UserIdentifierResolver::class);
        $this->app->bind(IdentifierNormalizerInterface::class, IdentifierNormalizer::class);
        $this->app->bind(UserAccessGuardInterface::class, UserAccessGuard::class);
        $this->app->bind(CommandActorLockInterface::class, UserCommandActorLock::class);

        // Credentials, invitations, OTP, sessions
        $this->app->bind(InvitationRepositoryInterface::class, EloquentInvitationRepository::class);
        $this->app->bind(CredentialRepositoryInterface::class, EloquentCredentialRepository::class);
        $this->app->bind(SessionRepositoryInterface::class, EloquentSessionRepository::class);
        $this->app->bind(OtpChallengeRepositoryInterface::class, EloquentOtpChallengeRepository::class);
        $this->app->singleton(SessionRegistryInterface::class, RedisSessionRegistry::class);
        $this->app->singleton(DeliveryCipherInterface::class, LaravelDeliveryCipher::class);
        $this->app->singleton(SessionSettingsInterface::class, LaravelSessionSettings::class);
        $this->app->singleton(AccessSessionValidatorInterface::class, DatabaseAccessSessionValidator::class);
        $this->app->bind(CredentialWriterInterface::class, CredentialWriter::class);
        $this->app->bind(SessionLifecycleInterface::class, SessionLifecycle::class);
        $this->app->bind(SessionIssuerInterface::class, SessionIssuer::class);
        $this->app->bind(VerificationProofConsumerInterface::class, VerificationProofConsumer::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
