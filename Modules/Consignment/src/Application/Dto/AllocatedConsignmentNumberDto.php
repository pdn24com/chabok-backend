<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Dto;

final readonly class AllocatedConsignmentNumberDto
{
    public function __construct(public string $rangeId, public string $consignmentNumber) {}
}
