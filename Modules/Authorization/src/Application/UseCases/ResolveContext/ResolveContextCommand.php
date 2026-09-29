<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ResolveContext;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ResolveContextCommand
{
    public function __construct(public AuthenticatedPrincipal $principal) {}
}
