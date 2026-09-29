<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Operations\Infrastructure\Persistence\Models\CoveragePolicyVersionRecord;

interface CoverageVersionGuardInterface
{
    public function expected(CoveragePolicyVersionRecord $row, int $expected): void;

    public function validatedChanges(string $hq, CoveragePolicyVersionRecord $row, string $user): array;

    public function simpleChanges(CoveragePolicyVersionRecord $row, string $from, string $to, array $extra = []): array;

    public function archiveChanges(CoveragePolicyVersionRecord $row): array;

    public function publishedChanges(string $hq, CoveragePolicyVersionRecord $row, string $user): array;

    public function assertDates(CoveragePolicyVersionRecord $row): void;
}
