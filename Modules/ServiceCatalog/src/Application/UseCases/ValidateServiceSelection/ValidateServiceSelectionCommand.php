<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ValidateServiceSelection;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Domain\ValueObjects\OfferingSelectionContext;

final readonly class ValidateServiceSelectionCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $offeringId,
        public ?string $versionId,
        public OfferingSelectionContext $context,
        public bool $requireCommitmentSelection = true,
    ) {}
}
