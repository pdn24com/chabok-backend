<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\CreateInvitation;

final readonly class CreateInvitationCommand
{
    public function __construct(public array $user, public string $channel, public ?string $actorId, public string $correlationId)
    {
    }
}
