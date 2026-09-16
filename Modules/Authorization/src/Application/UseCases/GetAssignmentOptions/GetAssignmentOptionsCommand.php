<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\GetAssignmentOptions;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetAssignmentOptionsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public ?string $roleId = null)
    {
    }
}
