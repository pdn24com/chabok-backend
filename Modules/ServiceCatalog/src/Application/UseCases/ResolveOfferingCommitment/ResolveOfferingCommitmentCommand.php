<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ResolveOfferingCommitment;

final readonly class ResolveOfferingCommitmentCommand
{
    public function __construct(public string $offeringVersionId, public array $context, public bool $requireSelection = true)
    {
    }
}
