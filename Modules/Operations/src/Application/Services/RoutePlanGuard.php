<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class RoutePlanGuard
{
    public function __construct(private \Modules\Operations\Application\Repositories\MovementRepository $plans)
    {
    }

    public function version(object $row, int $expected, string $label): void
    {
        if ((int) $row->version !== $expected) {
            throw new ApiException(ApiErrorCode::VersionConflict, 409, "The {$label} version is stale.", details: ['current_version' => (int) $row->version]);
        }
    }

    public function assertPlanConfigurationAvailable(object $plan): void
    {
        $evidence = $this->plans->configurationEvidence($plan->hq_id, $plan->route_plan_id, $plan->route_definition_version_id);
        $version = $plan->route_definition_version_id === null ? null : $this->plans->configurationVersion($plan->hq_id, $plan->route_definition_id, $plan->route_definition_version_id);
        $coverageVersionStatus = $evidence === null ? null : $this->plans->coverageVersionStatus($plan->hq_id, $evidence->coverage_policy_version_id);
        $legsAvailable = $this->plans->sourceLegsAvailable($plan->route_plan_id);
        if ($evidence === null || $version === null || !in_array($coverageVersionStatus, ['PUBLISHED', 'SUPERSEDED'], true) || !in_array($version->status, ['PUBLISHED', 'SUPERSEDED'], true) || !$legsAvailable) {
            throw new ApiException(ApiErrorCode::ConfigVersionUnavailable, 422, 'The Route Plan configuration snapshot is unavailable.');
        }
    }
}
