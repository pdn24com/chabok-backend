<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\ActivatePassword;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Iam\Application\Contracts\CredentialWriterInterface;
use Modules\Iam\Application\Contracts\VerificationProofConsumerInterface;
use Modules\Iam\Application\Repositories\InvitationRepositoryInterface;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;
use Modules\Iam\Domain\Enums\InvitationStatus;
use Modules\Iam\Domain\Enums\OtpPurpose;
use Modules\Iam\Domain\Policies\PasswordPolicy;
use Modules\Iam\Domain\Support\OpaqueToken;

final readonly class ActivatePasswordHandler
{
    public function __construct(
        private PasswordPolicy $passwordPolicy,
        private ConnectionInterface $connection,
        private VerificationProofConsumerInterface $verificationProofConsumer,
        private ClockInterface $clock,
        private CredentialWriterInterface $credentialWriter,
        private AuditWriterInterface $auditWriter,
        private InvitationRepositoryInterface $invitationRepository,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function handle(ActivatePasswordCommand $command): ActivatePasswordResult
    {
        $verificationToken = $command->verificationToken;
        $invitationToken = $command->invitationToken;
        $newPassword = $command->newPassword;
        $correlationId = $command->correlationId;
        if (($verificationToken === null) === ($invitationToken === null)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', ['verification_token' => ['iam.exactly_one_activation_proof_is_required']]);
        }
        $this->passwordPolicy->assertValid($newPassword);
        $result = $this->connection->transaction(function () use ($verificationToken, $invitationToken, $newPassword, $correlationId): ?string {
            if ($verificationToken !== null) {
                $userId = $this->verificationProofConsumer->consumeVerificationToken($verificationToken, OtpPurpose::ACTIVATION);
            } else {
                $hash = OpaqueToken::hash((string) $invitationToken);
                $invitation = $this->invitationRepository->lockByToken($hash);
                if ($invitation === null || $invitation->status !== InvitationStatus::PENDING) {
                    return null;
                }
                if ($invitation->expires_at->getTimestamp() <= $this->clock->now()->getTimestamp()) {
                    $this->invitationRepository->transitionTo($invitation, InvitationStatus::EXPIRED);

                    return null;
                }
                $userId = (string) $invitation->user_id;
                $this->invitationRepository->transitionTo($invitation, InvitationStatus::ACCEPTED, $this->clock->now());
            }
            $this->credentialWriter->upsertPassword($userId, $newPassword);
            $this->userRepository->update($userId, [
                'status' => 'ACTIVE',
                'must_change_password' => false,
                'activated_at' => $this->clock->now(),
            ]);
            $user = $this->userRepository->find($userId);
            $this->auditWriter->write($user->hq_id ?? null, $userId, 'USER_ACTIVATED', 'USER', $userId, $correlationId);

            return $userId;
        }, attempts: 3);
        if ($result === null) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'iam.activation_proof_is_invalid');
        }

        return new ActivatePasswordResult($result);
    }
}
