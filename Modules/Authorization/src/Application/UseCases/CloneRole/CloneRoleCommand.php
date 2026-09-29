<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\CloneRole;

use Modules\Authorization\Application\Dto\RoleDraftDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CloneRoleCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $sourceRoleId,
        public RoleDraftDto $input,
        public string $correlationId,
    ) {}
}
