<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\CoveragePolicyReaderInterface;
use Modules\Operations\Application\Dto\CoverageRuleDto;
use Modules\Operations\Application\Repositories\CoverageRepositoryInterface;
use Modules\Operations\Infrastructure\Persistence\Models\CoveragePolicyVersionRecord;

final readonly class CoveragePolicyReader implements CoveragePolicyReaderInterface
{
    public function __construct(
        private CoverageRepositoryInterface $coverageRepository,
    ) {}

    /** @return list<CoverageRuleDto> */
    public function ruleInputs(string $hq, string $versionId): array
    {
        $version = $this->coverageRepository->findVersionWithRules($hq, $versionId);
        if ($version === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'operations.source_version_not_found');
        }

        return $version->rules->map(CoverageRuleDto::fromRecord(...))->all();
    }

    public function versionRow(
        string $hq,
        string $policy,
        string $version,
    ): CoveragePolicyVersionRecord {
        $row = $this->coverageRepository->findPolicyVersionWithRules($hq, $policy, $version);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $row;
    }

    public function lockedVersion(
        string $hq,
        string $policy,
        string $version,
    ): CoveragePolicyVersionRecord {
        $row = $this->coverageRepository->lockPolicyVersionWithRules($hq, $policy, $version);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $row;
    }
}
