<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ValidateServiceSelection;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ValidateServiceSelectionCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $offeringId,
        public ?string $versionId,
        public array $context,
        public bool $requireCommitmentSelection = true,
    )
    {
    }
}
