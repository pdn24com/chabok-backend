<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\CreateRole;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CreateRoleCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $input, public string $correlationId)
    {
    }
}
