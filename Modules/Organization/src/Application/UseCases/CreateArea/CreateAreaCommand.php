<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\CreateArea;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CreateAreaCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $input, public string $correlationId)
    {
    }
}
