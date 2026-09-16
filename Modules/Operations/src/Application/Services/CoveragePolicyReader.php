<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CoveragePolicyReader
{
    public function __construct(private \Modules\Operations\Application\Repositories\CoveragePolicyRepository $coverage)
    {
    }

    public function presentVersion(object $row): array
    {
        return $this->versionArray($row);
    }

    public function ruleInputs(string $hq, string $versionId): array
    {
        if (!$this->coverage->versionExists($hq, $versionId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Source version not found.');
        }
        return array_map(fn($row): array => array_diff_key($this->ruleArray($row), ['coverage_rule_id' => true]), $this->coverage->rulesInCreationOrder($versionId));
    }

    public function versionRow(string $hq, string $policy, string $version): object
    {
        $row = $this->coverage->version($hq, $policy, $version);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return $row;
    }

    public function lockedVersion(string $hq, string $policy, string $version): object
    {
        $row = $this->coverage->lockVersion($hq, $policy, $version);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return $row;
    }

    public function policyArray(object $row): array
    {
        return [
            'coverage_policy_id' => (string) $row->coverage_policy_id,
            'policy_code' => (string) $row->policy_code,
            'policy_title' => (string) $row->policy_title,
            'published_version_id' => $row->published_version_id ? (string) $row->published_version_id : null,
        ];
    }

    public function versionArray(object $row): array
    {
        return [
            'coverage_policy_version_id' => (string) $row->coverage_policy_version_id,
            'coverage_policy_id' => (string) $row->coverage_policy_id,
            'version_number' => (int) $row->version_number,
            'status' => (string) $row->status,
            'effective_from' => $row->effective_from,
            'effective_to' => $row->effective_to,
            'version' => (int) $row->version,
            'rules' => array_map(fn($rule): array => $this->ruleArray($rule), $this->coverage->rulesByPriority($row->coverage_policy_version_id)),
        ];
    }

    public function ruleArray(object $row): array
    {
        $criterion = match ($row->criterion_type) {
            'PROVINCE' => ['criterion_type' => 'PROVINCE', 'province_id' => (string) $row->province_id],
            'CITY' => ['criterion_type' => 'CITY', 'city_id' => (string) $row->city_id],
            'POSTAL_RANGE' => [
                'criterion_type' => 'POSTAL_RANGE',
                'postal_code_from' => (string) $row->postal_code_from,
                'postal_code_to' => (string) $row->postal_code_to,
            ],
            'POLYGON' => [
                'criterion_type' => 'POLYGON',
                'geometry' => json_decode($row->geometry_geojson, true, 512, JSON_THROW_ON_ERROR),
            ],
            default => [
                'criterion_type' => 'POINT_RADIUS',
                'center' => ['latitude' => (float) $row->center_latitude, 'longitude' => (float) $row->center_longitude],
                'radius_meters' => (int) $row->radius_meters,
            ],
        };
        return [
            'coverage_rule_id' => (string) $row->coverage_rule_id,
            'target' => (string) $row->target,
            'target_node_id' => (string) $row->target_node_id,
            'priority' => (int) $row->priority,
            'offering_version_id' => $row->offering_version_id ? (string) $row->offering_version_id : null,
            'criterion' => $criterion,
        ];
    }
}
