<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Services;

use Modules\Foundation\Application\Contracts\Clock;
use Modules\Foundation\Application\Contracts\IdentifierGenerator;
use Modules\Identity\Application\Repositories\CredentialRepository;

final readonly class CredentialWriter
{
    public function __construct(private CredentialRepository $credentials, private IdentifierGenerator $ids, private Clock $clock)
    {
    }

    public function upsertPassword(string $userId, string $password): void
    {
        $this->credentials->upsert([
            'credential_id' => $this->ids->uuid(),
            'user_id' => $userId,
            'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
            'algorithm' => 'argon2id',
            'algorithm_version' => 1,
            'password_changed_at' => $this->clock->now(),
            'failed_attempt_count' => 0,
            'locked_until' => null,
            'created_at' => $this->clock->now(),
            'updated_at' => $this->clock->now(),
        ]);
    }
}
