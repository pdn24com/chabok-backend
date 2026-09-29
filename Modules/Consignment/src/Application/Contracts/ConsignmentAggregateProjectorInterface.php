<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Contracts;

use Modules\Consignment\Application\Dto\AggregateProjectionDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface ConsignmentAggregateProjectorInterface
{
    /** @param list<string> $consignmentIds */
    public function projectMany(AuthenticatedPrincipal $actor, array $consignmentIds, AggregateProjectionDto $projection): void;

    public function project(
        AuthenticatedPrincipal $actor,
        string $consignmentId,
        string $targetStatus,
        ?string $nodeId,
        ?string $manifestId,
        string $reasonCode,
        ?string $driverId = null,
        ?string $correlationId = null,
    ): void;
}
