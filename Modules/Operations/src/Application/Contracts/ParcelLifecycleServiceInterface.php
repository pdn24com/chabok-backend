<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface ParcelLifecycleServiceInterface
{
    /** @return list<string> parcel IDs */
    public function transition(AuthenticatedPrincipal $actor, string $consignmentId, string $from, string $to, string $command, ?string $nodeId, string $custodyType, ?string $custodianId, string $correlationId, ?string $driverId = null, ?string $manifestId = null, ?string $reasonCode = null, ?string $safeNote = null, ?string $routePlanId = null, ?string $routePlanLegId = null): array;
}
