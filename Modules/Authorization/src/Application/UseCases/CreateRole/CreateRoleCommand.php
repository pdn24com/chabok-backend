<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\CreateRole;

use Modules\Authorization\Application\Dto\RoleDraftDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CreateRoleCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public RoleDraftDto $input,
        public string $correlationId,
    ) {}
}
