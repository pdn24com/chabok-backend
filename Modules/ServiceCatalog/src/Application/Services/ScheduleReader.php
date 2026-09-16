<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\ServiceCatalog\Application\SchedulePolicy;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ScheduleReader
{
    public function __construct(private \Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepository $schedules)
    {
    }

    public function versionDetail(AuthenticatedPrincipal $actor, string $versionId): array
    {
        $row = $this->schedules->detail($actor->hqId, $versionId);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        $result = (array) $row;
        $result['commitment_policy'] = empty($row->commitment_policy) ? null : json_decode($row->commitment_policy, true);
        if ($result['commitment_policy'] === null) {
            $bindings = $this->schedules->legacyBindings($row->commitment_schedule_id);
            $policies = [];
            foreach ($bindings as $binding) {
                $policy = SchedulePolicy::fromBinding((array) $binding);
                $policies[json_encode($policy)] = $policy;
            }
            $result['legacy_policy_candidates'] = array_values($policies);
        }
        $result['windows'] = array_map(fn($window) => $this->decodeWindow((array) $window), $this->schedules->orderedWindows($versionId));
        $result['scopes'] = array_map(fn($scope) => (array) $scope, $this->schedules->scopes($versionId));
        return $result;
    }

    public function decodeWindow(array $window): array
    {
        if (is_string($window['applicable_weekdays'] ?? null)) {
            $window['applicable_weekdays'] = json_decode($window['applicable_weekdays'], true);
        }
        return $window;
    }
}
