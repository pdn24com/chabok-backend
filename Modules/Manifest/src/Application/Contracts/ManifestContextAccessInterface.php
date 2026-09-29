<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Domain\Enums\DriverCapability;

interface ManifestContextAccessInterface
{
    public function accessibleNodeIds(AuthenticatedPrincipal $actor): array;

    public function assertDriver(AuthenticatedPrincipal $actor, string $id, DriverCapability $capability): void;

    public function assertVehicle(string $hq, string $node, string $id): void;

    public function assertTargetNode(AuthenticatedPrincipal $actor, string $targetNodeId): void;
}
