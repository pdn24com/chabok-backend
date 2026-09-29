<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\PreviewServiceCommitment;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Domain\ValueObjects\OfferingSelectionContext;

final readonly class PreviewServiceCommitmentCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $offeringId,
        public OfferingSelectionContext $context,
    ) {}
}
