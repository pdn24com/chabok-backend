<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\UpdatePricingZoneVersion;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class UpdatePricingZoneVersionCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $versionId, public array $input)
    {
    }
}
