<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Operations\Domain\ValueObjects\CoverageLocation;

interface DestinationResolutionInputInterface
{
    public function destinationResolutionInput(ConsignmentRecord $consignment): CoverageLocation;
}
