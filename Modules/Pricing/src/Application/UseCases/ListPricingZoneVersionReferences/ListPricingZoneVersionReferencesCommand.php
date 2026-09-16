<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListPricingZoneVersionReferences;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListPricingZoneVersionReferencesCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $filters)
    {
    }
}
