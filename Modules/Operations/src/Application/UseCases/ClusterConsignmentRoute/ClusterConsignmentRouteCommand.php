<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ClusterConsignmentRoute;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ClusterConsignmentRouteCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $consignmentId,
        public int $expectedPlanVersion,
        public string $correlationId,
    ) {}
}
