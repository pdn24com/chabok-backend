<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ListRoles;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListRolesCommand
{
    public function __construct(public AuthenticatedPrincipal $actor) {}
}
