<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;

interface ManifestEvidenceRepositoryInterface
{
    /** @param list<string> $consignmentIds @return array<string, int> */
    public function statusSequenceHeads(?string $hqId, array $consignmentIds): array;

    /** @param list<string> $consignmentIds @return array<string, int> */
    public function custodySequenceHeads(?string $hqId, array $consignmentIds): array;

    /** @param list<array<string, mixed>> $rows */
    public function insertStatusEvents(array $rows): void;

    /** @param list<array<string, mixed>> $rows */
    public function insertCustodyEvents(array $rows): void;

    /** Status history of one Manifest, with everything its timeline renders. @return Collection<int, object> */
    public function statusHistory(?string $hqId, string $manifestId): Collection;

    /** @return Collection<int, object> */
    public function auditTrail(?string $hqId, string $manifestId): Collection;
}
