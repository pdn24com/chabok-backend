<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Dto;

final readonly class ParcelTransitionDto
{
    public function __construct(
        public string $fromStatus,
        public string $toStatus,
        public string $command,
        public ?string $nodeId,
        public string $custodyType,
        public ?string $custodianId,
        public string $actorId,
        public string $correlationId,
        public ?string $driverId,
        public ?string $manifestId,
        public string $reasonCode,
        public ?string $safeNote,
        public ?string $routePlanId,
        public ?string $routePlanLegId,
    ) {}
}
