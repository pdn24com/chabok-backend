<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ResolveOfferingCommitment;

use Modules\ServiceCatalog\Domain\ValueObjects\OfferingSelectionContext;

final readonly class ResolveOfferingCommitmentCommand
{
    public function __construct(
        public string $offeringVersionId,
        public OfferingSelectionContext $context,
        public bool $requireSelection = true,
    ) {}
}
