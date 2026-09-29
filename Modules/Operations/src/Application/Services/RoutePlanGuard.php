<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\RoutePlanGuardInterface;
use Modules\Operations\Domain\Enums\ConfigVersionStatus;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanRecord;

final class RoutePlanGuard implements RoutePlanGuardInterface
{
    public function version(RoutePlanRecord $row, int $expected, string $label): void
    {
        if ($row->version !== $expected) {
            throw new ApiException(ApiErrorCode::VersionConflict, 409, 'operations.version_is_stale', messageParams: ['label' => $label], details: ['current_version' => $row->version]);
        }
    }

    public function assertPlanConfigurationAvailable(RoutePlanRecord $plan): void
    {
        $plan->loadMissing(['evidence.coverageVersion', 'definitionVersion']);
        $evidence = $plan->evidence;
        $version = $plan->definitionVersion;
        $coverage = $evidence?->coverageVersion;
        $legsAvailable = $plan->legs()->whereDoesntHave('sourceVersionLeg', fn ($source) => $source->whereColumn('route_definition_version_legs.hq_id', 'route_plan_legs.hq_id'))->doesntExist();
        if ($evidence === null || $version === null || $coverage === null
            || $evidence->hq_id !== $plan->hq_id || $evidence->route_definition_version_id !== $plan->route_definition_version_id
            || $version->hq_id !== $plan->hq_id || $version->route_definition_id !== $plan->route_definition_id
            || $coverage->hq_id !== $plan->hq_id
            || ! in_array($coverage->status, [ConfigVersionStatus::Published->value, ConfigVersionStatus::Superseded->value], true)
            || ! in_array($version->status, [ConfigVersionStatus::Published->value, ConfigVersionStatus::Superseded->value], true) || ! $legsAvailable) {
            throw new ApiException(ApiErrorCode::ConfigVersionUnavailable, 422, 'operations.route_plan_configuration_snapshot_is_unavailable');
        }
    }
}
