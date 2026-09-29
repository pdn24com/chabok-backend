<?php

declare(strict_types=1);

namespace Modules\Iam\Infrastructure\Repositories;

use Modules\Iam\Application\Repositories\OtpChallengeRepositoryInterface;
use Modules\Iam\Domain\Enums\OtpPurpose;
use Modules\Iam\Infrastructure\Persistence\Models\OtpChallengeRecord;

final class EloquentOtpChallengeRepository implements OtpChallengeRepositoryInterface
{
    public function findWithUser(string $challengeId): ?OtpChallengeRecord
    {
        return OtpChallengeRecord::query()->with('user')->where('challenge_id', $challengeId)->first();
    }

    public function lockById(string $challengeId): ?OtpChallengeRecord
    {
        return OtpChallengeRecord::query()->where('challenge_id', $challengeId)->lockForUpdate()->first();
    }

    public function lockByVerificationToken(string $tokenHash, OtpPurpose $purpose): ?OtpChallengeRecord
    {
        return OtpChallengeRecord::query()->where('verification_token_hash', $tokenHash)->where('purpose', $purpose)->lockForUpdate()->first();
    }

    public function create(array $attributes): OtpChallengeRecord
    {
        return OtpChallengeRecord::query()->forceCreate($attributes);
    }

    public function apply(OtpChallengeRecord $challenge, array $changes): void
    {
        $challenge->forceFill($changes)->save();
    }
}
