<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\UpdateNode;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class UpdateNodeCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public array $input,
        public string $correlationId,
    )
    {
    }
}
