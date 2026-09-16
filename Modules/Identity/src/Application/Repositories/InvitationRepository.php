<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Repositories;

interface InvitationRepository
{
    public function findByHashForUpdate(string $hash): ?\stdClass;

    public function insert(array $attributes): void;

    public function update(string $invitationId, array $attributes): void;

    public function supersedePending(string $userId, string $channel, \DateTimeImmutable $at): void;
}
