<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\UpdateRole;

use Modules\Authorization\Application\Dto\RoleChangesDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class UpdateRoleCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $roleId,
        public RoleChangesDto $input,
        public string $correlationId,
    ) {}
}
