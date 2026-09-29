<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Repositories;

use DateTimeInterface;
use Modules\Iam\Domain\Enums\InvitationStatus;
use Modules\Iam\Infrastructure\Persistence\Models\InvitationRecord;

interface InvitationRepositoryInterface
{
    public function lockByToken(string $tokenHash): ?InvitationRecord;

    /** Latest invitation status for a user, newest first, or null when never invited. */
    public function latestStatusForUser(string $userId): ?string;

    public function supersedePending(string $userId, string $channel): void;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): InvitationRecord;

    public function transitionTo(InvitationRecord $invitation, InvitationStatus $status, ?DateTimeInterface $acceptedAt = null): void;
}
