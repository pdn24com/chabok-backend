<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\CreatePricingZoneSet;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CreatePricingZoneSetCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $input, public string $correlationId)
    {
    }
}
