<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Repositories;

use Modules\Identity\Application\Repositories\OtpChallengeRepository;
use Modules\Identity\Infrastructure\Persistence\Models\OtpChallengeRecord;

final class EloquentOtpChallengeRepository implements OtpChallengeRepository
{
    public function findForUpdate(string $challengeId): ?\stdClass
    {
        $row = OtpChallengeRecord::query()->where('challenge_id', $challengeId)->lockForUpdate()->first();
        return $row === null ? null : (object) $row->getAttributes();
    }

    public function findVerificationForUpdate(string $hash, string $purpose): ?\stdClass
    {
        $row = OtpChallengeRecord::query()->where('verification_token_hash', $hash)->where('purpose', $purpose)->lockForUpdate()->first();
        return $row === null ? null : (object) $row->getAttributes();
    }

    public function insert(array $attributes): void
    {
        OtpChallengeRecord::query()->insert($attributes);
    }

    public function update(string $challengeId, array $attributes): void
    {
        OtpChallengeRecord::query()->where('challenge_id', $challengeId)->update($attributes);
    }
}
