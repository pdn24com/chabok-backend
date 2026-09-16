<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ListEntitlements;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListEntitlementsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $correlationId)
    {
    }
}
