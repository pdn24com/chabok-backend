<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListPricingChargeTypes;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListPricingChargeTypesCommand
{
    public function __construct(public AuthenticatedPrincipal $actor) {}
}
