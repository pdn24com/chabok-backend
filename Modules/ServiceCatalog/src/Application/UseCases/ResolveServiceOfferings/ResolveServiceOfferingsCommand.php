<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ResolveServiceOfferings;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ResolveServiceOfferingsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $context)
    {
    }
}
