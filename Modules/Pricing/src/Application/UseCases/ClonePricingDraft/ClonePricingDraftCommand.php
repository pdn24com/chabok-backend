<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ClonePricingDraft;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ClonePricingDraftCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $kind,
        public string $identityId,
        public string $correlationId,
    )
    {
    }
}
