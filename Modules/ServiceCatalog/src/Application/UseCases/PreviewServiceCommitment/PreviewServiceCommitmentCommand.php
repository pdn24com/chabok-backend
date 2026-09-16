<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\PreviewServiceCommitment;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class PreviewServiceCommitmentCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $offeringId, public array $context)
    {
    }
}
