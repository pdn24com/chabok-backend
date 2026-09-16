<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\UpdateRole;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class UpdateRoleCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $roleId,
        public array $input,
        public string $correlationId,
    )
    {
    }
}
