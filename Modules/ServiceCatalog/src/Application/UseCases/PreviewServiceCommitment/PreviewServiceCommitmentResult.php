<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\PreviewServiceCommitment;

final readonly class PreviewServiceCommitmentResult
{
    public function __construct(public array $data)
    {
    }
}
