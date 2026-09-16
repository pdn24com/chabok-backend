<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\GetUser;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetUserCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $userId)
    {
    }
}
