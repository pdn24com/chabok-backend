<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\TransitionPricingVersion;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class TransitionPricingVersionCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $kind,
        public string $versionId,
        public string $action,
        public string $correlationId,
    )
    {
    }
}
