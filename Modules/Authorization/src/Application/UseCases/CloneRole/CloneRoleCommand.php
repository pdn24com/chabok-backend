<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\CloneRole;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CloneRoleCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $sourceRoleId,
        public array $input,
        public string $correlationId,
    )
    {
    }
}
