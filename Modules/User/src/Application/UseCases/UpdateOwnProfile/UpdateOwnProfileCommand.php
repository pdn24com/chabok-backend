<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\UpdateOwnProfile;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class UpdateOwnProfileCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $input, public string $correlationId)
    {
    }
}
