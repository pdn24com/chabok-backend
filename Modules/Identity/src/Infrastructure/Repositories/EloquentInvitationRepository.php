<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Repositories;

use Modules\Identity\Application\Repositories\InvitationRepository;
use Modules\Identity\Infrastructure\Persistence\Models\InvitationRecord;

final class EloquentInvitationRepository implements InvitationRepository
{
    public function findByHashForUpdate(string $hash): ?\stdClass
    {
        $row = InvitationRecord::query()->where('token_hash', $hash)->lockForUpdate()->first();
        return $row === null ? null : (object) $row->getAttributes();
    }

    public function insert(array $attributes): void
    {
        InvitationRecord::query()->insert($attributes);
    }

    public function update(string $invitationId, array $attributes): void
    {
        InvitationRecord::query()->where('invitation_id', $invitationId)->update($attributes);
    }

    public function supersedePending(string $userId, string $channel, \DateTimeImmutable $at): void
    {
        InvitationRecord::query()->where('user_id', $userId)->where('channel', $channel)->where('status', 'PENDING')->update(['status' => 'SUPERSEDED', 'updated_at' => $at]);
    }
}
