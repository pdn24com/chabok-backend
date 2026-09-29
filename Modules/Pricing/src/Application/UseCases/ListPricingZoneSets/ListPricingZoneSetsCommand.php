<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListPricingZoneSets;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Pricing\Application\Dto\PricingFiltersDto;

final readonly class ListPricingZoneSetsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public PricingFiltersDto $filters) {}
}
