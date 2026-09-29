<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentWindowInstance;

final readonly class PickupWindowOptionDto
{
    public function __construct(public string $scheduleVersionId, public CommitmentWindowInstance $window) {}
}
