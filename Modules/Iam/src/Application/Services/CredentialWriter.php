<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Services;

use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Iam\Application\Contracts\CredentialWriterInterface;
use Modules\Iam\Application\Repositories\CredentialRepositoryInterface;

final readonly class CredentialWriter implements CredentialWriterInterface
{
    public function __construct(
        private ClockInterface $clock,
        private CredentialRepositoryInterface $credentialRepository,
    ) {}

    public function upsertPassword(string $userId, string $password): void
    {
        $this->credentialRepository->replaceForUser([

            'user_id' => $userId,
            'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
            'algorithm' => 'argon2id',
            'algorithm_version' => 1,
            'password_changed_at' => $this->clock->now(),
            'failed_attempt_count' => 0,
            'locked_until' => null,
        ]);
    }
}
