<?php

declare(strict_types=1);

namespace Modules\Iam\Infrastructure\Repositories;

use DateTimeInterface;
use Modules\Iam\Application\Repositories\InvitationRepositoryInterface;
use Modules\Iam\Domain\Enums\InvitationStatus;
use Modules\Iam\Infrastructure\Persistence\Models\InvitationRecord;

final class EloquentInvitationRepository implements InvitationRepositoryInterface
{
    public function lockByToken(string $tokenHash): ?InvitationRecord
    {
        return InvitationRecord::query()->where('token_hash', $tokenHash)->lockForUpdate()->first();
    }

    public function latestStatusForUser(string $userId): ?string
    {
        return InvitationRecord::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->first()?->status->value;
    }

    public function supersedePending(string $userId, string $channel): void
    {
        InvitationRecord::query()->where('user_id', $userId)->where('channel', $channel)
            ->where('status', InvitationStatus::PENDING)->update(['status' => InvitationStatus::SUPERSEDED]);
    }

    public function create(array $attributes): InvitationRecord
    {
        return InvitationRecord::query()->forceCreate($attributes);
    }

    public function transitionTo(InvitationRecord $invitation, InvitationStatus $status, ?DateTimeInterface $acceptedAt = null): void
    {
        $invitation->forceFill(['status' => $status] + ($acceptedAt === null ? [] : ['accepted_at' => $acceptedAt]))->save();
    }
}
