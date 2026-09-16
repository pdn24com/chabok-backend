<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ValidatePricingZoneSet;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ValidatePricingZoneSetCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $versionId)
    {
    }
}
