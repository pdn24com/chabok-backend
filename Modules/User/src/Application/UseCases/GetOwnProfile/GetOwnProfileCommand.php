<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\GetOwnProfile;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetOwnProfileCommand
{
    public function __construct(public AuthenticatedPrincipal $actor)
    {
    }
}
