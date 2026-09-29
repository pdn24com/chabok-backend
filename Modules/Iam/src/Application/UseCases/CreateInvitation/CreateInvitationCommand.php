<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\CreateInvitation;

use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

final readonly class CreateInvitationCommand
{
    public function __construct(
        public UserRecord $user,
        public string $channel,
        public ?string $actorId,
        public string $correlationId,
    ) {}
}
