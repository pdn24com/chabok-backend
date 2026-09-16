<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

/** Module-owned state for Manifest orchestration; the caller owns the transaction. */

interface ManifestDirectoryReader
{
    public function nodesByIds(array $referenceIds, string $hq): array;

    public function activeNodes(string $hq, array $accessibleNodeIds): array;

    public function node(?string $hqId, string $targetNodeId): ?object;

    public function activeNode(string $hq, string $node): ?object;

    public function OperationalContextNode(string $hq, ?string $id): ?object;

    public function provinceForActiveCity(?string $receiverCityId): ?string;
}
