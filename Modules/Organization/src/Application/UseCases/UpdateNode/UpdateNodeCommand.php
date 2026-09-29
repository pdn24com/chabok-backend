<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\UpdateNode;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Organization\Application\Dto\NodeChangesDto;

final readonly class UpdateNodeCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public NodeChangesDto $input,
        public string $correlationId,
    ) {}
}
