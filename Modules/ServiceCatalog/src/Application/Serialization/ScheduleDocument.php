<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Serialization;

use Modules\ServiceCatalog\Application\Services\SchedulePolicy;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleVersionRecord;

final class ScheduleDocument
{
    /** All relations are loaded by the caller; rendering never performs a lookup. */
    public static function version(CommitmentScheduleVersionRecord $version): array
    {
        $result = $version->attributesToArray();
        $result['code'] = $version->schedule->code;
        $result['title'] = $version->schedule->title;
        $result['identity_status'] = $version->schedule->status;
        if ($version->commitment_policy === null) {
            $policies = [];
            foreach ($version->schedule->legacyBindings as $binding) {
                $policy = SchedulePolicy::fromBinding($binding->attributesToArray());
                $policies[json_encode($policy)] = $policy;
            }
            $result['legacy_policy_candidates'] = array_values($policies);
        }
        $result['windows'] = $version->windows->map(fn ($window) => $window->attributesToArray())->all();
        $result['scopes'] = $version->scopes->map(fn ($scope) => $scope->attributesToArray())->all();

        return $result;
    }
}
