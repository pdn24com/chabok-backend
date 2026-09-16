<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ListPickupCommitmentWindows;

final readonly class ListPickupCommitmentWindowsResult
{
    public function __construct(public array $data)
    {
    }
}
