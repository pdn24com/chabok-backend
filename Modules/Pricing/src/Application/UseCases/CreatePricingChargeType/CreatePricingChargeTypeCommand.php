<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\CreatePricingChargeType;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Pricing\Application\Dto\PricingChargeTypeDto;

final readonly class CreatePricingChargeTypeCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public PricingChargeTypeDto $input) {}
}
