<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\CreateInvitation;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Iam\Application\Contracts\DeliveryCipherInterface;
use Modules\Iam\Application\Repositories\InvitationRepositoryInterface;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;
use Modules\Iam\Domain\Enums\InvitationStatus;
use Modules\Iam\Domain\Support\OpaqueToken;

final readonly class CreateInvitationHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private OutboxWriterInterface $outboxWriter,
        private DeliveryCipherInterface $deliveryCipher,
        private InvitationRepositoryInterface $invitationRepository,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function handle(CreateInvitationCommand $command): CreateInvitationResult
    {
        $user = $command->user;
        $channel = $command->channel;
        $actorId = $command->actorId;
        $correlationId = $command->correlationId;
        $recipient = $channel === 'SMS' ? $user->normalized_mobile ?? null : $user->normalized_email ?? null;
        if (! is_string($recipient) || $recipient === '') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', [strtolower($channel) => ['iam.invitation_channel_is_unavailable']]);
        }
        $this->connection->transaction(function () use ($user, $channel, $actorId, $correlationId, $recipient): void {
            $this->userRepository->lock((string) $user->user_id);
            $this->invitationRepository->supersedePending((string) $user->user_id, $channel);
            $raw = OpaqueToken::generate();
            $invitationId = (string) $this->invitationRepository->create([

                'hq_id' => $user->hq_id,
                'user_id' => $user->user_id,
                'channel' => $channel,
                'normalized_recipient' => $recipient,
                'token_hash' => OpaqueToken::hash($raw),
                'status' => InvitationStatus::PENDING,
                'sent_at' => $this->clock->now(),
                'expires_at' => $this->clock->now()->modify('+2 days'),
                'created_by' => $actorId,
            ])->getKey();
            $this->outboxWriter->write($user->hq_id, 'USER_INVITATION', $invitationId, 'identity.invitation.delivery.requested', $correlationId, [
                'invitation_id' => $invitationId,
                'channel' => $channel,
                'recipient_fingerprint' => hash('sha256', $recipient),
                'delivery_ciphertext' => $this->deliveryCipher->encrypt($raw),
            ]);
        }, attempts: 3);

        return new CreateInvitationResult;
    }
}
