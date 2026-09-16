<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\CreateInvitation;

use Modules\Foundation\Application\Contracts\Clock;
use Modules\Foundation\Application\Contracts\IdentifierGenerator;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Identity\Application\Contracts\DeliveryCipher;
use Modules\Identity\Application\Repositories\InvitationRepository;
use Modules\Identity\Domain\OpaqueToken;
use Modules\User\Application\Repositories\UserRepository;

final readonly class CreateInvitationHandler
{
    public function __construct(
        private TransactionManager $transactions,
        private UserRepository $users,
        private InvitationRepository $invitations,
        private Clock $clock,
        private IdentifierGenerator $ids,
        private OutboxWriter $outbox,
        private DeliveryCipher $cipher,
    )
    {
    }

    public function handle(CreateInvitationCommand $command): CreateInvitationResult
    {
        $this->execute($command->user, $command->channel, $command->actorId, $command->correlationId);
        return new CreateInvitationResult();
    }

    private function execute(array $user, string $channel, ?string $actorId, string $correlationId): void
    {
        $recipient = $channel === 'SMS' ? $user['normalized_mobile'] ?? null : $user['normalized_email'] ?? null;
        if (!is_string($recipient) || $recipient === '') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The request is invalid.', [strtolower($channel) => ['The requested invitation channel is unavailable.']]);
        }
        $this->transactions->run(function () use ($user, $channel, $actorId, $correlationId, $recipient): void {
            $this->users->findForUpdate($user['user_id']);
            $this->invitations->supersedePending($user['user_id'], $channel, $this->clock->now());
            $raw = OpaqueToken::generate();
            $invitationId = $this->ids->uuid();
            $this->invitations->insert([
                'invitation_id' => $invitationId,
                'hq_id' => $user['hq_id'],
                'user_id' => $user['user_id'],
                'channel' => $channel,
                'normalized_recipient' => $recipient,
                'token_hash' => OpaqueToken::hash($raw),
                'status' => 'PENDING',
                'sent_at' => $this->clock->now(),
                'expires_at' => $this->clock->now()->modify('+2 days'),
                'created_by' => $actorId,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $this->outbox->write($user['hq_id'], 'USER_INVITATION', $invitationId, 'identity.invitation.delivery.requested', $correlationId, [
                'invitation_id' => $invitationId,
                'channel' => $channel,
                'recipient_fingerprint' => hash('sha256', $recipient),
                'delivery_ciphertext' => $this->cipher->encrypt($raw),
            ]);
        });
    }
}
