<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Repositories;

use Modules\Iam\Domain\Enums\OtpPurpose;
use Modules\Iam\Infrastructure\Persistence\Models\OtpChallengeRecord;

interface OtpChallengeRepositoryInterface
{
    /** Loads the challenge together with its User, because the delivery channel follows the User's contacts. */
    public function findWithUser(string $challengeId): ?OtpChallengeRecord;

    public function lockById(string $challengeId): ?OtpChallengeRecord;

    public function lockByVerificationToken(string $tokenHash, OtpPurpose $purpose): ?OtpChallengeRecord;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): OtpChallengeRecord;

    /** @param array<string, mixed> $changes */
    public function apply(OtpChallengeRecord $challenge, array $changes): void;
}
