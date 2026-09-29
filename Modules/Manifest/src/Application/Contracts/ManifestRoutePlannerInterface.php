<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Contracts;

use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Application\Dto\ManifestRouteReferenceDto;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanRecord;

interface ManifestRoutePlannerInterface
{
    public function ensureRoute(AuthenticatedPrincipal $actor, string $node, ParcelRecord $p, string $correlationId): ManifestRouteReferenceDto;

    public function createPlan(AuthenticatedPrincipal $actor, string $node, string $consignmentId, string $correlationId): RoutePlanRecord;
}
