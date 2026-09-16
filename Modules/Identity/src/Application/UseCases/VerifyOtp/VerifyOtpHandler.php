<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\VerifyOtp;

use Modules\Foundation\Application\Contracts\Clock;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Identity\Application\Repositories\OtpChallengeRepository;
use Modules\Identity\Domain\OpaqueToken;

final readonly class VerifyOtpHandler
{
    public function __construct(
        private TransactionManager $transactions,
        private OtpChallengeRepository $challenges,
        private Clock $clock,
    )
    {
    }

    public function handle(VerifyOtpCommand $command): VerifyOtpResult
    {
        return new VerifyOtpResult($this->execute($command->challengeId, $command->code));
    }

    private function execute(string $challengeId, string $code): array
    {
        $result = $this->transactions->run(function () use ($challengeId, $code): array {
            $challenge = $this->challenges->findForUpdate($challengeId);
            if ($challenge === null || $challenge->status !== 'PENDING' || strtotime((string) $challenge->expires_at) <= $this->clock->now()->getTimestamp() || !password_verify($code, (string) $challenge->code_hash)) {
                if ($challenge !== null && $challenge->status === 'PENDING') {
                    if (strtotime((string) $challenge->expires_at) <= $this->clock->now()->getTimestamp()) {
                        $this->challenges->update($challengeId, ['status' => 'EXPIRED']);
                    } else {
                        $attempts = max(0, (int) $challenge->remaining_attempts - 1);
                        $this->challenges->update($challengeId, ['remaining_attempts' => $attempts, 'status' => $attempts === 0 ? 'LOCKED' : 'PENDING']);
                    }
                }
                return ['invalid' => true];
            }
            if ($challenge->user_id === null) {
                return ['invalid' => true];
            }
            $raw = OpaqueToken::generate();
            $ttl = 600;
            $this->challenges->update($challengeId, [
                'verification_token_hash' => OpaqueToken::hash($raw),
                'status' => 'VERIFIED',
                'verified_at' => $this->clock->now(),
                'expires_at' => $this->clock->now()->modify(sprintf('%+d seconds', $ttl)),
            ]);
            return ['verification_token' => $raw, 'expires_in' => $ttl];
        });
        if (($result['invalid'] ?? false) === true) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Verification code is invalid.');
        }
        return $result;
    }
}
