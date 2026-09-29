<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Services;

use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Iam\Application\Contracts\VerificationProofConsumerInterface;
use Modules\Iam\Application\Repositories\OtpChallengeRepositoryInterface;
use Modules\Iam\Domain\Enums\OtpPurpose;
use Modules\Iam\Domain\Enums\OtpStatus;
use Modules\Iam\Domain\Support\OpaqueToken;

final readonly class VerificationProofConsumer implements VerificationProofConsumerInterface
{
    public function __construct(
        private ClockInterface $clock,
        private OtpChallengeRepositoryInterface $otpChallengeRepository,
    ) {}

    public function consumeVerificationToken(
        string $raw,
        OtpPurpose $purpose,
        ?string $expectedUserId = null,
    ): string {
        $row = $this->otpChallengeRepository->lockByVerificationToken(OpaqueToken::hash($raw), $purpose);
        if ($row === null || $row->status !== OtpStatus::VERIFIED || $row->expires_at->getTimestamp() <= $this->clock->now()->getTimestamp() || $expectedUserId !== null && $row->user_id !== $expectedUserId) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'iam.verification_proof_is_invalid');
        }
        $this->otpChallengeRepository->apply($row, ['status' => OtpStatus::CONSUMED, 'consumed_at' => $this->clock->now()]);

        return (string) $row->user_id;
    }
}
