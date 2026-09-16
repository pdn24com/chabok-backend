<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\SendOtp;

use Modules\Foundation\Application\Contracts\Clock;
use Modules\Foundation\Application\Contracts\IdentifierGenerator;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Application\Contracts\SecurityMetricRecorder;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Identity\Application\Contracts\DeliveryCipher;
use Modules\Identity\Application\Data\IdentityData;
use Modules\Identity\Application\Repositories\OtpChallengeRepository;
use Modules\User\Application\Repositories\UserRepository;

final readonly class SendOtpHandler
{
    public function __construct(
        private UserRepository $users,
        private SecurityMetricRecorder $metrics,
        private IdentifierGenerator $ids,
        private TransactionManager $transactions,
        private OtpChallengeRepository $challenges,
        private Clock $clock,
        private OutboxWriter $outbox,
        private DeliveryCipher $cipher,
    )
    {
    }

    public function handle(SendOtpCommand $command): SendOtpResult
    {
        return new SendOtpResult($this->execute($command->identifier, $command->purpose, $command->correlationId, $command->ip));
    }

    private function execute(string $identifier, string $purpose, string $correlationId, ?string $ip): array
    {
        $user = $this->users->findByIdentifier($identifier);
        $this->metrics->increment('auth.otp_requested', ['purpose' => $purpose]);
        $challengeId = $this->ids->uuid();
        $code = (string) random_int(100000, 999999);
        $ttl = 600;
        $destinationFingerprint = hash('sha256', mb_strtolower(trim($identifier)));
        return $this->transactions->run(function () use ($challengeId, $user, $destinationFingerprint, $purpose, $code, $ttl, $ip, $correlationId): array {
            $this->challenges->insert([
                'challenge_id' => $challengeId,
                'user_id' => $user['user_id'] ?? null,
                'destination_fingerprint' => $destinationFingerprint,
                'purpose' => $purpose,
                'code_hash' => password_hash($code, PASSWORD_ARGON2ID),
                'expires_at' => $this->clock->now()->modify(sprintf('%+d seconds', $ttl)),
                'remaining_attempts' => 5,
                'status' => 'PENDING',
                'request_ip_hash' => IdentityData::fingerprint($ip),
                'created_at' => $this->clock->now(),
            ]);
            if ($user !== null) {
                $this->outbox->write($user['hq_id'], 'OTP_CHALLENGE', $challengeId, 'identity.otp.delivery.requested', $correlationId, [
                    'challenge_id' => $challengeId,
                    'purpose' => $purpose,
                    'delivery_ciphertext' => $this->cipher->encrypt($code),
                    'destination_fingerprint' => $destinationFingerprint,
                ]);
            } else {
                // Deliberately comparable cryptographic work for unknown identifiers.
                $this->cipher->encrypt($code);
            }
            return ['challenge_id' => $challengeId, 'expires_in' => $ttl];
        });
    }
}
