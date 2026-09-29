<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\VerifyOtp;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Iam\Application\Repositories\OtpChallengeRepositoryInterface;
use Modules\Iam\Domain\Enums\OtpStatus;
use Modules\Iam\Domain\Support\OpaqueToken;

final readonly class VerifyOtpHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private OtpChallengeRepositoryInterface $otpChallengeRepository,
    ) {}

    public function handle(VerifyOtpCommand $command): VerifyOtpResult
    {
        $challengeId = $command->challengeId;
        $code = $command->code;
        $result = $this->connection->transaction(function () use ($challengeId, $code): ?VerifyOtpResult {
            $challenge = $this->otpChallengeRepository->lockById($challengeId);
            if ($challenge === null || $challenge->status !== OtpStatus::PENDING || $challenge->expires_at->getTimestamp() <= $this->clock->now()->getTimestamp() || ! password_verify($code, (string) $challenge->code_hash)) {
                if ($challenge !== null && $challenge->status === OtpStatus::PENDING) {
                    if ($challenge->expires_at->getTimestamp() <= $this->clock->now()->getTimestamp()) {
                        $this->otpChallengeRepository->apply($challenge, ['status' => OtpStatus::EXPIRED]);
                    } else {
                        $attempts = max(0, (int) $challenge->remaining_attempts - 1);
                        $this->otpChallengeRepository->apply($challenge, ['remaining_attempts' => $attempts, 'status' => $attempts === 0 ? OtpStatus::LOCKED : OtpStatus::PENDING]);
                    }
                }

                return null;
            }
            if ($challenge->user_id === null) {
                return null;
            }
            $raw = OpaqueToken::generate();
            $ttl = 600;
            $this->otpChallengeRepository->apply($challenge, [
                'verification_token_hash' => OpaqueToken::hash($raw),
                'status' => OtpStatus::VERIFIED,
                'verified_at' => $this->clock->now(),
                'expires_at' => $this->clock->now()->modify(sprintf('%+d seconds', $ttl)),
            ]);

            return new VerifyOtpResult($raw, $ttl);
        }, attempts: 3);
        if ($result === null) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'iam.verification_code_is_invalid');
        }

        return $result;
    }
}
