<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Contracts;

use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;

interface ManifestTargetApplierInterface
{
    /** @param list<ParcelRecord> $parcels @return array<string, \Modules\Manifest\Application\Dto\ManifestParcelTransitionDto> */
    public function applyTargets(AuthenticatedPrincipal $actor, string $node, ManifestRecord $manifest, array $parcels, string $correlationId): array;
}
