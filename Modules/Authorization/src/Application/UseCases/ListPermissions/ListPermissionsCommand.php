<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ListPermissions;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListPermissionsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public ?string $moduleCode)
    {
    }
}
