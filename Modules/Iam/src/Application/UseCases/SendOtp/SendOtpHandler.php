<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\SendOtp;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Contracts\SecurityMetricRecorderInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Iam\Application\Contracts\DeliveryCipherInterface;
use Modules\Iam\Application\Contracts\UserIdentifierResolverInterface;
use Modules\Iam\Application\Dto\IdentityDto;
use Modules\Iam\Application\Repositories\OtpChallengeRepositoryInterface;
use Modules\Iam\Domain\Enums\OtpStatus;

final readonly class SendOtpHandler
{
    public function __construct(
        private UserIdentifierResolverInterface $userIdentifierResolver,
        private SecurityMetricRecorderInterface $securityMetricRecorder,
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private OutboxWriterInterface $outboxWriter,
        private DeliveryCipherInterface $deliveryCipher,
        private OtpChallengeRepositoryInterface $otpChallengeRepository,
    ) {}

    public function handle(SendOtpCommand $command): SendOtpResult
    {
        $identifier = $command->identifier;
        $purpose = $command->purpose;
        $correlationId = $command->correlationId;
        $ip = $command->ip;
        $user = $this->userIdentifierResolver->findByIdentifier($identifier);
        $this->securityMetricRecorder->increment('auth.otp_requested', ['purpose' => $purpose->value]);
        $code = (string) random_int(100000, 999999);
        $ttl = 600;
        $destinationFingerprint = hash('sha256', mb_strtolower(trim($identifier)));

        return $this->connection->transaction(function () use ($user, $destinationFingerprint, $purpose, $code, $ttl, $ip, $correlationId): SendOtpResult {
            $challengeId = (string) $this->otpChallengeRepository->create([

                'user_id' => $user->user_id ?? null,
                'destination_fingerprint' => $destinationFingerprint,
                'purpose' => $purpose,
                'code_hash' => password_hash($code, PASSWORD_ARGON2ID),
                'expires_at' => $this->clock->now()->modify(sprintf('%+d seconds', $ttl)),
                'remaining_attempts' => 5,
                'status' => OtpStatus::PENDING,
                'request_ip_hash' => IdentityDto::fingerprint($ip),
            ])->getKey();
            if ($user !== null) {
                $this->outboxWriter->write($user->hq_id, 'OTP_CHALLENGE', $challengeId, 'identity.otp.delivery.requested', $correlationId, [
                    'challenge_id' => $challengeId,
                    'purpose' => $purpose->value,
                    'delivery_ciphertext' => $this->deliveryCipher->encrypt($code),
                    'destination_fingerprint' => $destinationFingerprint,
                ]);
            } else {
                // Deliberately comparable cryptographic work for unknown identifiers.
                $this->deliveryCipher->encrypt($code);
            }

            return new SendOtpResult($challengeId, $ttl);
        }, attempts: 3);
    }
}
