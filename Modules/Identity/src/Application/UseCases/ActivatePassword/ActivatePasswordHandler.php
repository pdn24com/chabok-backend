<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\ActivatePassword;

use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\Clock;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Identity\Application\Repositories\InvitationRepository;
use Modules\Identity\Application\Services\CredentialWriter;
use Modules\Identity\Application\Services\VerificationProofConsumer;
use Modules\Identity\Domain\OpaqueToken;
use Modules\Identity\Domain\PasswordPolicy;
use Modules\User\Application\Repositories\UserRepository;

final readonly class ActivatePasswordHandler
{
    public function __construct(
        private PasswordPolicy $passwordPolicy,
        private TransactionManager $transactions,
        private VerificationProofConsumer $verificationProofConsumer,
        private InvitationRepository $invitations,
        private Clock $clock,
        private CredentialWriter $credentialWriter,
        private UserRepository $users,
        private AuditWriter $audit,
    )
    {
    }

    public function handle(ActivatePasswordCommand $command): ActivatePasswordResult
    {
        return new ActivatePasswordResult($this->execute($command->verificationToken, $command->invitationToken, $command->newPassword, $command->correlationId));
    }

    private function execute(?string $verificationToken, ?string $invitationToken, string $newPassword, string $correlationId): string
    {
        if (($verificationToken === null) === ($invitationToken === null)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The request is invalid.', ['verification_token' => ['Exactly one activation proof is required.']]);
        }
        $this->passwordPolicy->assertValid($newPassword);
        $result = $this->transactions->run(function () use ($verificationToken, $invitationToken, $newPassword, $correlationId): string|array {
            if ($verificationToken !== null) {
                $userId = $this->verificationProofConsumer->consumeVerificationToken($verificationToken, 'ACTIVATION');
            } else {
                $hash = OpaqueToken::hash((string) $invitationToken);
                $invitation = $this->invitations->findByHashForUpdate($hash);
                if ($invitation === null || $invitation->status !== 'PENDING') {
                    return ['invalid_proof' => true];
                }
                if (strtotime((string) $invitation->expires_at) <= $this->clock->now()->getTimestamp()) {
                    $this->invitations->update($invitation->invitation_id, ['status' => 'EXPIRED', 'updated_at' => $this->clock->now()]);
                    return ['invalid_proof' => true];
                }
                $userId = (string) $invitation->user_id;
                $this->invitations->update($invitation->invitation_id, ['status' => 'ACCEPTED', 'accepted_at' => $this->clock->now(), 'updated_at' => $this->clock->now()]);
            }
            $this->credentialWriter->upsertPassword($userId, $newPassword);
            $this->users->update($userId, [
                'status' => 'ACTIVE',
                'must_change_password' => false,
                'activated_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $user = $this->users->findById($userId);
            $this->audit->write($user['hq_id'] ?? null, $userId, 'USER_ACTIVATED', 'USER', $userId, $correlationId);
            return $userId;
        });
        if (is_array($result)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Activation proof is invalid.');
        }
        return $result;
    }
}
