<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Operations\Application\Dto\CoverageRuleDto;
use Modules\Operations\Infrastructure\Persistence\Models\CoveragePolicyVersionRecord;

interface CoveragePolicyReaderInterface
{
    /** @return list<CoverageRuleDto> */
    public function ruleInputs(string $hq, string $versionId): array;

    public function versionRow(string $hq, string $policy, string $version): CoveragePolicyVersionRecord;

    public function lockedVersion(string $hq, string $policy, string $version): CoveragePolicyVersionRecord;
}
