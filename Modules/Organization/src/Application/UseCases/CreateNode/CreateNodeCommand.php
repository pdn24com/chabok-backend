<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\CreateNode;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Organization\Application\Dto\NodeDraftDto;

final readonly class CreateNodeCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public NodeDraftDto $input,
        public string $correlationId,
    ) {}
}
