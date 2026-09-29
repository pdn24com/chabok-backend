<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

interface ManifestContextOptionsInterface
{
    public function operationalOptions(AuthenticatedPrincipal $actor, string $nodeId, NodeRecord $node): array;

    public function pickupOptions(string $hq, string $node): array;

    public function routeLegOptions(string $hq, string $node): array;

    public function movementReceptionOptions(string $hq, string $node): array;

    public function deliveryOptions(string $hq, string $node): array;

    public function draftOptions(AuthenticatedPrincipal $actor, string $nodeId): array;
}
