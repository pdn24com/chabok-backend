<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\PlanConsignmentRoute;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class PlanConsignmentRouteCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $consignmentId,
        public string $correlationId,
    ) {}
}
