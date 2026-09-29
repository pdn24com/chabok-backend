<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Geography\Application\Repositories\CityRepositoryInterface;
use Modules\Geography\Application\Repositories\ProvinceRepositoryInterface;
use Modules\Operations\Application\Contracts\CoverageRuleGuardInterface;
use Modules\Operations\Application\Dto\CoverageRuleDto;
use Modules\Operations\Application\Serialization\CoveragePolicyDocument;
use Modules\Operations\Domain\Enums\CoverageCriterionType;
use Modules\Operations\Domain\Policies\CoverageCriterionPolicy;
use Modules\Organization\Application\Repositories\NodeRepositoryInterface;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;
use Modules\ServiceCatalog\Domain\Enums\CatalogResource;

final class CoverageRuleGuard implements CoverageRuleGuardInterface
{
    public function __construct(
        private NodeRepositoryInterface $nodeRepository,
        private CatalogRepositoryInterface $catalogRepository,
        private ProvinceRepositoryInterface $provinceRepository,
        private CityRepositoryInterface $cityRepository,
    ) {}

    /** @param list<CoverageRuleDto> $rules */
    public function validateRuleInputs(string $hq, array $rules): void
    {
        if ($rules === []) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.least_one_coverage_rule_is_required');
        }
        $nodeIds = $offeringIds = $provinceIds = $cityIds = [];
        foreach ($rules as $rule) {
            $nodeIds[] = $rule->targetNodeId;
            if ($rule->offeringVersionId !== null) {
                $offeringIds[] = $rule->offeringVersionId;
            }
            if ($rule->criterion->type === CoverageCriterionType::PROVINCE) {
                $provinceIds[] = $rule->criterion->provinceId;
            }
            if ($rule->criterion->type === CoverageCriterionType::CITY) {
                $cityIds[] = $rule->criterion->cityId;
            }
        }
        $nodes = collect($this->nodeRepository->activeIdsAmong($hq, $nodeIds))->flip();
        $offerings = $offeringIds === [] ? collect() : collect($this->catalogRepository->visibleVersionIdsAmong(CatalogResource::Offering, $offeringIds, $hq))->flip();
        $provinces = $provinceIds === [] ? collect() : collect($this->provinceRepository->idsAmong($provinceIds))->flip();
        $cities = $cityIds === [] ? collect() : collect($this->cityRepository->idsAmong($cityIds))->flip();
        $signatures = [];
        foreach ($rules as $rule) {
            $criterion = $rule->criterion;
            if ($criterion->priority < -100000 || $criterion->priority > 100000) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.coverage_priority_is_outside_supported_range');
            }
            if (! $nodes->has($rule->targetNodeId)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.every_target_node_must_be_active_belong');
            }
            if ($rule->offeringVersionId !== null && ! $offerings->has($rule->offeringVersionId)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.offering_version_is_not_visible_hq');
            }
            if (! CoverageCriterionPolicy::valid($criterion)
                || ($criterion->type === CoverageCriterionType::PROVINCE && ! $provinces->has($criterion->provinceId))
                || ($criterion->type === CoverageCriterionType::CITY && ! $cities->has($criterion->cityId))) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.coverage_criterion_is_invalid');
            }
            $signature = hash('sha256', json_encode([$rule->target->value, $criterion->priority, $rule->offeringVersionId, CoveragePolicyDocument::criterion($criterion)], JSON_THROW_ON_ERROR));
            if (isset($signatures[$signature])) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.duplicate_indistinguishable_coverage_rules_are_not_allowed');
            }
            $signatures[$signature] = true;
        }
    }
}
