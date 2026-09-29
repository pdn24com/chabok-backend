<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListPricingZoneVersionReferences;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Pricing\Application\Dto\PricingZoneVersionSearchDto;

final readonly class ListPricingZoneVersionReferencesCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public PricingZoneVersionSearchDto $search) {}
}
