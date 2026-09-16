<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ActivateManifestDelivery;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ActivateManifestDeliveryCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $consignmentId,
        public string $driverId,
        public string $manifestId,
    )
    {
    }
}
