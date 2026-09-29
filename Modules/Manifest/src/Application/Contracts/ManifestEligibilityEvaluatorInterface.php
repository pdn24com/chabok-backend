<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Manifest\Application\Dto\ManifestCandidateScopeDto;
use Modules\Manifest\Domain\Enums\ManifestEligibilityReason;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;

interface ManifestEligibilityEvaluatorInterface
{
    /**
     * @param  Collection<int, ParcelRecord>  $parcels
     * @return array<string, ManifestEligibilityReason>
     */
    public function evaluateMany(Collection $parcels, ManifestRecord $manifest, string $nodeId): array;

    public function candidateScope(ManifestRecord $manifest, string $nodeId): ManifestCandidateScopeDto;
}
