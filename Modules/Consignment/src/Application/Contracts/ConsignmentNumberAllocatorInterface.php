<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Contracts;

use Modules\Consignment\Application\Dto\AllocatedConsignmentNumberDto;

interface ConsignmentNumberAllocatorInterface
{
    public function next(string $hqId): AllocatedConsignmentNumberDto;

    public function record(
        string $hqId,
        string $rangeId,
        string $consignmentId,
        string $number,
        string $actorId,
        string $correlationId,
    ): void;
}
