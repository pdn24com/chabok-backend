<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Repositories;

interface OtpChallengeRepository
{
    public function findForUpdate(string $challengeId): ?\stdClass;

    public function findVerificationForUpdate(string $hash, string $purpose): ?\stdClass;

    public function insert(array $attributes): void;

    public function update(string $challengeId, array $attributes): void;
}
