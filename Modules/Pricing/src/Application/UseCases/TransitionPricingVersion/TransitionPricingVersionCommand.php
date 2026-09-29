<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\TransitionPricingVersion;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Pricing\Domain\Enums\PricingResource;
use Modules\Pricing\Domain\Enums\PricingTransition;

final readonly class TransitionPricingVersionCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public PricingResource $kind,
        public string $versionId,
        public PricingTransition $action,
        public string $correlationId,
    ) {}
}
