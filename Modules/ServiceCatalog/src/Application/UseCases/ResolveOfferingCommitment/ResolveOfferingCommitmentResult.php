<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ResolveOfferingCommitment;

final readonly class ResolveOfferingCommitmentResult
{
    public function __construct(public ?array $data)
    {
    }
}
