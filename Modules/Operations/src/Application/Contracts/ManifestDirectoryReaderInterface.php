<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

/** Module-owned state for Manifest orchestration; the caller owns the transaction. */
use Illuminate\Database\Eloquent\Collection;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

interface ManifestDirectoryReaderInterface
{
    public function nodesByIds(array $referenceIds, string $hq): Collection;

    public function activeNodes(string $hq, array $accessibleNodeIds): Collection;

    public function node(?string $hqId, string $targetNodeId): ?NodeRecord;

    public function activeNode(string $hq, string $node): ?NodeRecord;

    public function operationalContextNode(string $hq, ?string $id): ?NodeRecord;

    public function provinceForActiveCity(?string $receiverCityId): ?string;
}
