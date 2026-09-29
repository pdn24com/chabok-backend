<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListPricingAuditEvents;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Pricing\Application\Dto\PricingFiltersDto;

final readonly class ListPricingAuditEventsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public PricingFiltersDto $filters) {}
}
