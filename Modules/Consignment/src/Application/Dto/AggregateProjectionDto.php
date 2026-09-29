<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Dto;

final readonly class AggregateProjectionDto
{
    public function __construct(
        public string $targetStatus,
        public ?string $nodeId,
        public ?string $manifestId,
        public string $reasonCode,
        public ?string $driverId = null,
        public ?string $correlationId = null,
    ) {}
}
