<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ClonePricingDraft;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Pricing\Domain\Enums\PricingResource;

final readonly class ClonePricingDraftCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public PricingResource $kind,
        public string $identityId,
        public string $correlationId,
    ) {}
}
