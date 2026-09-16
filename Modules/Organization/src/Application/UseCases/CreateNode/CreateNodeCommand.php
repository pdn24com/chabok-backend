<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\CreateNode;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CreateNodeCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $input, public string $correlationId)
    {
    }
}
