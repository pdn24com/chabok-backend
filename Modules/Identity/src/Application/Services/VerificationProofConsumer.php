<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Services;

use Modules\Foundation\Application\Contracts\Clock;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Identity\Application\Repositories\OtpChallengeRepository;
use Modules\Identity\Domain\OpaqueToken;

final readonly class VerificationProofConsumer
{
    public function __construct(private OtpChallengeRepository $challenges, private Clock $clock)
    {
    }

    public function consumeVerificationToken(string $raw, string $purpose, ?string $expectedUserId = null): string
    {
        $row = $this->challenges->findVerificationForUpdate(OpaqueToken::hash($raw), $purpose);
        if ($row === null || $row->status !== 'VERIFIED' || strtotime((string) $row->expires_at) <= $this->clock->now()->getTimestamp() || $expectedUserId !== null && $row->user_id !== $expectedUserId) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Verification proof is invalid.');
        }
        $this->challenges->update($row->challenge_id, ['status' => 'CONSUMED', 'consumed_at' => $this->clock->now()]);
        return (string) $row->user_id;
    }
}
