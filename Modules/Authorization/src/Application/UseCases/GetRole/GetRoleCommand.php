<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\GetRole;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class GetRoleCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $roleId) {}
}
