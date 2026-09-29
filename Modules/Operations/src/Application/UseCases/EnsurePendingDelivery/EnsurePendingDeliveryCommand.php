<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\EnsurePendingDelivery;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class EnsurePendingDeliveryCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $consignmentId,
    ) {}
}
