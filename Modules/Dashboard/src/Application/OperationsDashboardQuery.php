<?php

declare(strict_types=1);

namespace Modules\Dashboard\Application;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Dashboard\Domain\DashboardMetricDefinitions;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class OperationsDashboardQuery
{
    private const LIMIT = 6;

    public function __construct(
        private AuthorizationContextResolver $authorization,
    ) {}

    /** @return array<string, mixed> */
    public function get(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }

        $context = $this->authorization->resolve($actor);
        $context['acting_node_id'] = $nodeId;
        $this->assertDashboardAccess($context, $nodeId);
        $node = DB::table('nodes')->where([
            'hq_id' => $actor->hqId,
            'node_id' => $nodeId,
            'status' => 'ACTIVE',
        ])->first(['node_id', 'node_code', 'node_title', 'node_type']);
        if ($node === null) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
        }

        $asOf = CarbonImmutable::now('UTC')->toISOString();
        $consignmentCapability = $this->capability($context, 'Consignment', 'consignment.view');
        $manifestCapability = $this->capability($context, 'Manifest', 'manifest.view');
        $pickupCapability = $this->capability($context, 'Pickup', 'pickup_request.view');
        $driverCapability = $this->capability($context, 'Driver', 'fleet.driver.view');
        $nokCapability = $this->capability($context, 'Exception', 'exception.nok.view');
        $npuCapability = $this->capability($context, 'Exception', 'exception.npu.view');

        $consignments = $this->consignmentSummary(
            $actor->hqId,
            $nodeId,
            $asOf,
            $consignmentCapability,
        );
        $manifests = $this->manifestSummary(
            $actor->hqId,
            $nodeId,
            $asOf,
            $manifestCapability,
        );
        $drivers = $this->driverSummary(
            $actor->hqId,
            $nodeId,
            $asOf,
            $driverCapability,
        );

        return [
            'status' => 'PARTIAL',
            'as_of' => $asOf,
            'node' => [
                'node_id' => (string) $node->node_id,
                'node_code' => (string) $node->node_code,
                'node_title' => (string) $node->node_title,
                'node_type' => (string) $node->node_type,
            ],
            'filters' => [
                $this->unavailableFilter(
                    'OPERATIONAL_DATE',
                    'OPERATIONAL_DATE_FILTER_UNSUPPORTED',
                    $asOf,
                ),
                $this->unavailableFilter('SHIFT', 'SHIFT_FILTER_UNSUPPORTED', $asOf),
            ],
            'metrics' => [
                $this->metric(
                    'ACTIVE_CONSIGNMENTS',
                    $consignmentCapability,
                    $consignments['active'],
                    $asOf,
                ),
                $this->metric(
                    'OPEN_MANIFESTS',
                    $manifestCapability,
                    $manifests['open'],
                    $asOf,
                ),
                $this->unavailableMetric(
                    'AWAITING_NOK_REVIEW',
                    $nokCapability,
                    'DATA_NOT_PERSISTED',
                    $asOf,
                ),
                $this->unavailableMetric(
                    'AWAITING_NPU_REVIEW',
                    $npuCapability,
                    'DATA_NOT_PERSISTED',
                    $asOf,
                ),
                $this->unavailableMetric(
                    'OPEN_PICKUP_REQUESTS',
                    $pickupCapability,
                    'MODULE_NOT_IMPLEMENTED',
                    $asOf,
                ),
                $this->unavailableMetric(
                    'ACTIVE_DELAYS',
                    $consignmentCapability,
                    'DATA_NOT_PERSISTED',
                    $asOf,
                ),
                $this->unavailableMetric(
                    'SLA_RISK',
                    $consignmentCapability,
                    'DATA_NOT_PERSISTED',
                    $asOf,
                ),
                $this->metric(
                    'ACTIVE_DRIVERS',
                    $driverCapability,
                    $drivers['total'],
                    $asOf,
                ),
            ],
            'attention' => $this->attention(
                $actor->hqId,
                $nodeId,
                $context,
                $asOf,
                $consignmentCapability,
                $manifestCapability,
            ),
            'consignments' => $consignments,
            'pickup_requests' => $this->unavailableDomain(
                $pickupCapability,
                'MODULE_NOT_IMPLEMENTED',
                $asOf,
            ),
            'manifests' => $manifests,
            'drivers' => $drivers,
            'latest_updates' => $this->latestUpdates(
                $actor->hqId,
                $nodeId,
                $context,
                $asOf,
                $consignmentCapability,
                $manifestCapability,
            ),
            'shortcuts' => $this->shortcuts($context),
        ];
    }

    /** @param array<string, mixed> $context */
    private function assertDashboardAccess(array $context, string $nodeId): void
    {
        $foundation = $this->capability($context, 'Foundation', 'branch_panel.access');
        if (! $foundation['available']) {
            $code = $foundation['reason'] === 'ENTITLEMENT_DISABLED'
                ? ApiErrorCode::EntitlementDisabled
                : ApiErrorCode::PermissionDenied;
            throw new ApiException($code, 403, 'Access denied.');
        }
        if (! in_array($nodeId, \Modules\Foundation\Application\ScopedAccess::nodes($context, 'branch_panel.access'), true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
        }
    }

    /**
     * @param array<string, mixed> $context
     * @return array{available: bool, reason: string|null}
     */
    private function capability(array $context, string $moduleCode, string $permission): array
    {
        $enabled = collect($context['module_entitlements'])
            ->contains(fn (array $item): bool => strcasecmp(
                (string) $item['module_code'],
                $moduleCode,
            ) === 0 && $item['status'] === 'ENABLED');
        if (! $enabled) {
            return ['available' => false, 'reason' => 'ENTITLEMENT_DISABLED'];
        }
        if (! in_array($permission, $context['permissions'], true) || ! in_array($context['acting_node_id'] ?? '', \Modules\Foundation\Application\ScopedAccess::nodes($context, $permission), true)) {
            return ['available' => false, 'reason' => 'PERMISSION_DENIED'];
        }

        return ['available' => true, 'reason' => null];
    }

    /**
     * @param array{available: bool, reason: string|null} $capability
     * @return array<string, mixed>
     */
    private function consignmentSummary(
        string $hqId,
        string $nodeId,
        string $asOf,
        array $capability,
    ): array {
        if (! $capability['available']) {
            return [
                'status' => 'UNAVAILABLE',
                'as_of' => $asOf,
                'reason_code' => $capability['reason'],
                'total' => null,
                'active' => null,
                'status_counts' => null,
            ];
        }

        $selects = ['COUNT(*) AS total'];
        foreach (DashboardMetricDefinitions::CONSIGNMENT_STATUSES as $status) {
            $selects[] = "SUM(CASE WHEN current_status = '{$status}' THEN 1 ELSE 0 END) AS s_{$status}";
        }
        $active = implode(
            "','",
            DashboardMetricDefinitions::ACTIVE_CONSIGNMENT_STATUSES,
        );
        $selects[] = "SUM(CASE WHEN current_status IN ('{$active}') THEN 1 ELSE 0 END) AS active";
        $row = (array) DB::table('consignments')
            ->where(['hq_id' => $hqId, 'pickup_node_id' => $nodeId])
            ->selectRaw(implode(', ', $selects))
            ->first();
        $statusCounts = [];
        foreach (DashboardMetricDefinitions::CONSIGNMENT_STATUSES as $status) {
            $statusCounts[$status] = (int) ($row["s_{$status}"] ?? 0);
        }

        return [
            'status' => 'AVAILABLE',
            'as_of' => $asOf,
            'reason_code' => null,
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
            'status_counts' => $statusCounts,
        ];
    }

    /**
     * @param array{available: bool, reason: string|null} $capability
     * @return array<string, mixed>
     */
    private function manifestSummary(
        string $hqId,
        string $nodeId,
        string $asOf,
        array $capability,
    ): array {
        if (! $capability['available']) {
            return [
                'status' => 'UNAVAILABLE',
                'as_of' => $asOf,
                'reason_code' => $capability['reason'],
                'total' => null,
                'open' => null,
                'failed_rows' => null,
                'state_counts' => null,
                'target_status_counts' => null,
            ];
        }

        $row = (array) DB::table('manifests')
            ->where(['hq_id' => $hqId, 'node_id' => $nodeId])
            ->selectRaw(
                "COUNT(*) AS total,
                SUM(CASE WHEN state = 'DRAFT' THEN 1 ELSE 0 END) AS state_DRAFT,
                SUM(CASE WHEN state = 'OPEN' THEN 1 ELSE 0 END) AS state_OPEN,
                SUM(CASE WHEN state = 'CLOSED' THEN 1 ELSE 0 END) AS state_CLOSED,
                SUM(CASE WHEN manifest_status = 'IR' THEN 1 ELSE 0 END) AS target_IR,
                SUM(CASE WHEN manifest_status = 'OF' THEN 1 ELSE 0 END) AS target_OF,
                SUM(CASE WHEN manifest_status = 'OD' THEN 1 ELSE 0 END) AS target_OD",
            )->first();
        $failedRows = DB::table('manifest_parcels as mp')
            ->join('manifests as m', function ($join): void {
                $join->on('m.manifest_id', '=', 'mp.manifest_id')
                    ->on('m.hq_id', '=', 'mp.hq_id');
            })
            ->where('m.hq_id', $hqId)
            ->where('m.node_id', $nodeId)
            ->where('mp.manifest_parcel_status', 'FAILED')
            ->count();

        return [
            'status' => 'AVAILABLE',
            'as_of' => $asOf,
            'reason_code' => null,
            'total' => (int) ($row['total'] ?? 0),
            'open' => (int) ($row['state_OPEN'] ?? 0),
            'failed_rows' => $failedRows,
            'state_counts' => [
                'DRAFT' => (int) ($row['state_DRAFT'] ?? 0),
                'OPEN' => (int) ($row['state_OPEN'] ?? 0),
                'CLOSED' => (int) ($row['state_CLOSED'] ?? 0),
            ],
            'target_status_counts' => [
                'IR' => (int) ($row['target_IR'] ?? 0),
                'OF' => (int) ($row['target_OF'] ?? 0),
                'OD' => (int) ($row['target_OD'] ?? 0),
            ],
        ];
    }

    /**
     * @param array{available: bool, reason: string|null} $capability
     * @return array<string, mixed>
     */
    private function driverSummary(
        string $hqId,
        string $nodeId,
        string $asOf,
        array $capability,
    ): array {
        if (! $capability['available']) {
            return [
                'status' => 'UNAVAILABLE',
                'as_of' => $asOf,
                'reason_code' => $capability['reason'],
                'total' => null,
                'status_counts' => null,
            ];
        }

        $row = (array) DB::table('drivers')
            ->where([
                'hq_id' => $hqId,
                'home_node_id' => $nodeId,
                'status' => 'ACTIVE',
            ])
            ->selectRaw(
                "COUNT(*) AS total,
                SUM(CASE WHEN availability_status = 'AVAILABLE' THEN 1 ELSE 0 END) AS status_AVAILABLE,
                SUM(CASE WHEN availability_status = 'ON_MISSION' THEN 1 ELSE 0 END) AS status_ON_MISSION",
            )->first();

        return [
            'status' => 'AVAILABLE',
            'as_of' => $asOf,
            'reason_code' => null,
            'total' => (int) ($row['total'] ?? 0),
            'status_counts' => [
                'AVAILABLE' => (int) ($row['status_AVAILABLE'] ?? 0),
                'ON_MISSION' => (int) ($row['status_ON_MISSION'] ?? 0),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @param array{available: bool, reason: string|null} $consignmentCapability
     * @param array{available: bool, reason: string|null} $manifestCapability
     * @return array<string, mixed>
     */
    private function attention(
        string $hqId,
        string $nodeId,
        array $context,
        string $asOf,
        array $consignmentCapability,
        array $manifestCapability,
    ): array {
        $items = [];
        if ($consignmentCapability['available']) {
            $canAct = in_array('consignment.edit', $context['permissions'], true);
            $rows = DB::table('consignments')
                ->where(['hq_id' => $hqId, 'pickup_node_id' => $nodeId])
                ->whereIn('current_status', ['NOK', 'NPU'])
                ->orderByDesc('updated_at')
                ->limit(self::LIMIT)
                ->get([
                    'consignment_id', 'consignment_number', 'current_status', 'updated_at',
                ]);
            foreach ($rows as $row) {
                $status = (string) $row->current_status;
                $items[] = [
                    'type' => "CONSIGNMENT_{$status}",
                    'severity' => $status === 'NOK' ? 'HIGH' : 'MEDIUM',
                    'title' => "{$status} operational status",
                    'entity_type' => 'CONSIGNMENT',
                    'public_identifier' => (string) $row->consignment_number,
                    'occurred_at' => $this->time($row->updated_at),
                    'navigation_target' => "/consignments/{$row->consignment_id}",
                    'action_available' => $canAct,
                    '_priority' => $status === 'NOK' ? 2 : 1,
                ];
            }
        }
        if ($manifestCapability['available']) {
            $canEdit = in_array('manifest.edit', $context['permissions'], true);
            $rows = DB::table('manifests as m')
                ->join('manifest_parcels as mp', function ($join): void {
                    $join->on('m.manifest_id', '=', 'mp.manifest_id')
                        ->on('m.hq_id', '=', 'mp.hq_id');
                })
                ->where('m.hq_id', $hqId)
                ->where('m.node_id', $nodeId)
                ->whereIn('m.state', ['DRAFT', 'OPEN'])
                ->where('mp.manifest_parcel_status', 'FAILED')
                ->groupBy('m.manifest_id', 'm.manifest_number', 'm.state', 'm.updated_at')
                ->select([
                    'm.manifest_id', 'm.manifest_number', 'm.state', 'm.updated_at',
                ])
                ->orderByDesc('m.updated_at')
                ->limit(self::LIMIT)
                ->get();
            foreach ($rows as $row) {
                $target = $canEdit
                    ? "/manifests/{$row->manifest_id}/command-station"
                    : "/manifests/{$row->manifest_id}";
                $items[] = [
                    'type' => 'MANIFEST_VALIDATION_FAILURE',
                    'severity' => 'MEDIUM',
                    'title' => 'Manifest validation failures',
                    'entity_type' => 'MANIFEST',
                    'public_identifier' => (string) $row->manifest_number,
                    'occurred_at' => $this->time($row->updated_at),
                    'navigation_target' => $target,
                    'action_available' => $canEdit,
                    '_priority' => 1,
                ];
            }
        }
        usort($items, static fn (array $left, array $right): int =>
            [$right['_priority'], $right['occurred_at']]
            <=> [$left['_priority'], $left['occurred_at']]);
        $items = array_slice($items, 0, self::LIMIT);
        $items = array_map(static function (array $item): array {
            unset($item['_priority']);

            return $item;
        }, $items);

        $availableCount = (int) $consignmentCapability['available']
            + (int) $manifestCapability['available'];
        if ($availableCount === 0) {
            return [
                'status' => 'UNAVAILABLE',
                'as_of' => $asOf,
                'reason_code' => 'SECTION_PERMISSION_FILTERED',
                'items' => [],
            ];
        }

        return [
            'status' => $availableCount === 2 ? 'AVAILABLE' : 'PARTIAL',
            'as_of' => $asOf,
            'reason_code' => $availableCount === 2 ? null : 'SECTION_PERMISSION_FILTERED',
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @param array{available: bool, reason: string|null} $consignmentCapability
     * @param array{available: bool, reason: string|null} $manifestCapability
     * @return array<string, mixed>
     */
    private function latestUpdates(
        string $hqId,
        string $nodeId,
        array $context,
        string $asOf,
        array $consignmentCapability,
        array $manifestCapability,
    ): array {
        $auditCapability = $this->capability($context, 'Audit', 'audit.view');
        if (! $auditCapability['available']) {
            return [
                'status' => 'UNAVAILABLE',
                'as_of' => $asOf,
                'reason_code' => $auditCapability['reason'],
                'items' => [],
            ];
        }
        if (! $consignmentCapability['available'] && ! $manifestCapability['available']) {
            return [
                'status' => 'UNAVAILABLE',
                'as_of' => $asOf,
                'reason_code' => 'NO_VISIBLE_AUDIT_TARGETS',
                'items' => [],
            ];
        }

        $items = [];
        if ($consignmentCapability['available']) {
            $rows = DB::table('audit_events as a')
                ->join('consignments as c', function ($join): void {
                    $join->on('c.consignment_id', '=', 'a.target_id')
                        ->on('c.hq_id', '=', 'a.hq_id');
                })
                ->where('a.hq_id', $hqId)
                ->where('a.target_type', 'CONSIGNMENT')
                ->where('c.pickup_node_id', $nodeId)
                ->whereIn('a.action_key', ['CONSIGNMENT_CREATED', 'CONSIGNMENT_UPDATED'])
                ->orderByDesc('a.created_at')
                ->limit(self::LIMIT)
                ->get([
                    'a.audit_id', 'a.action_key', 'a.created_at',
                    'c.consignment_id', 'c.consignment_number',
                ]);
            foreach ($rows as $row) {
                $items[] = [
                    'audit_id' => (string) $row->audit_id,
                    'action_key' => (string) $row->action_key,
                    'title' => $row->action_key === 'CONSIGNMENT_CREATED'
                        ? 'Consignment created'
                        : 'Consignment updated',
                    'entity_type' => 'CONSIGNMENT',
                    'public_identifier' => (string) $row->consignment_number,
                    'occurred_at' => $this->time($row->created_at),
                    'navigation_target' => "/consignments/{$row->consignment_id}",
                ];
            }
        }
        if ($manifestCapability['available']) {
            $titles = [
                'MANIFEST_CREATED' => 'Manifest created',
                'MANIFEST_CONTEXT_UPDATED' => 'Manifest context updated',
                'MANIFEST_PARCELS_ADDED' => 'Manifest parcels added',
                'MANIFEST_VALIDATED' => 'Manifest validated',
                'MANIFEST_CONFIRMED' => 'Manifest confirmed',
            ];
            $rows = DB::table('audit_events as a')
                ->join('manifests as m', function ($join): void {
                    $join->on('m.manifest_id', '=', 'a.target_id')
                        ->on('m.hq_id', '=', 'a.hq_id');
                })
                ->where('a.hq_id', $hqId)
                ->where('a.target_type', 'MANIFEST')
                ->where('m.node_id', $nodeId)
                ->whereIn('a.action_key', array_keys($titles))
                ->orderByDesc('a.created_at')
                ->limit(self::LIMIT)
                ->get([
                    'a.audit_id', 'a.action_key', 'a.created_at',
                    'm.manifest_id', 'm.manifest_number',
                ]);
            foreach ($rows as $row) {
                $items[] = [
                    'audit_id' => (string) $row->audit_id,
                    'action_key' => (string) $row->action_key,
                    'title' => $titles[(string) $row->action_key],
                    'entity_type' => 'MANIFEST',
                    'public_identifier' => (string) $row->manifest_number,
                    'occurred_at' => $this->time($row->created_at),
                    'navigation_target' => "/manifests/{$row->manifest_id}",
                ];
            }
        }
        usort(
            $items,
            static fn (array $left, array $right): int =>
                $right['occurred_at'] <=> $left['occurred_at'],
        );
        $items = array_slice($items, 0, self::LIMIT);
        $complete = $consignmentCapability['available'] && $manifestCapability['available'];

        return [
            'status' => $complete ? 'AVAILABLE' : 'PARTIAL',
            'as_of' => $asOf,
            'reason_code' => $complete ? null : 'SECTION_PERMISSION_FILTERED',
            'items' => $items,
        ];
    }

    /** @param array<string, mixed> $context
     *  @return list<array<string, mixed>>
     */
    private function shortcuts(array $context): array
    {
        $definitions = [
            ['CONSIGNMENTS', 'Consignment', 'consignment.view', '/consignments', true],
            ['CREATE_CONSIGNMENT', 'Consignment', 'consignment.create', '/consignments/new', true],
            ['MANIFESTS', 'Manifest', 'manifest.view', '/manifests', true],
            ['CREATE_MANIFEST', 'Manifest', 'manifest.create', '/manifests/new', true],
            ['PICKUP_REQUESTS', 'Pickup', 'pickup_request.view', null, false],
            ['DRIVERS', 'Driver', 'fleet.driver.view', '/app/administration/fleet/drivers', true],
            ['LIVE_OPERATIONS', 'LiveOperations', 'live_operations.view', null, false],
            ['EXCEPTIONS', 'Exception', 'exception.nok.view', null, false],
        ];

        return array_map(function (array $definition) use ($context): array {
            [$key, $module, $permission, $target, $implemented] = $definition;
            $capability = $this->capability($context, $module, $permission);
            if (! $capability['available']) {
                return [
                    'key' => $key,
                    'status' => 'UNAVAILABLE',
                    'reason_code' => $capability['reason'],
                    'navigation_target' => null,
                ];
            }

            return [
                'key' => $key,
                'status' => $implemented ? 'AVAILABLE' : 'UNAVAILABLE',
                'reason_code' => $implemented ? null : 'MODULE_NOT_IMPLEMENTED',
                'navigation_target' => $implemented ? $target : null,
            ];
        }, $definitions);
    }

    /**
     * @param array{available: bool, reason: string|null} $capability
     * @return array<string, mixed>
     */
    private function metric(
        string $key,
        array $capability,
        mixed $value,
        string $asOf,
    ): array {
        return [
            'key' => $key,
            'value' => $capability['available'] ? (int) $value : null,
            'status' => $capability['available'] ? 'AVAILABLE' : 'UNAVAILABLE',
            'as_of' => $asOf,
            'reason_code' => $capability['reason'],
            'comparison' => null,
        ];
    }

    /**
     * @param array{available: bool, reason: string|null} $capability
     * @return array<string, mixed>
     */
    private function unavailableMetric(
        string $key,
        array $capability,
        string $fallbackReason,
        string $asOf,
    ): array {
        return [
            'key' => $key,
            'value' => null,
            'status' => 'UNAVAILABLE',
            'as_of' => $asOf,
            'reason_code' => $capability['available']
                ? $fallbackReason
                : $capability['reason'],
            'comparison' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function unavailableFilter(string $key, string $reason, string $asOf): array
    {
        return [
            'key' => $key,
            'value' => null,
            'status' => 'UNAVAILABLE',
            'as_of' => $asOf,
            'reason_code' => $reason,
        ];
    }

    /**
     * @param array{available: bool, reason: string|null} $capability
     * @return array<string, mixed>
     */
    private function unavailableDomain(
        array $capability,
        string $fallbackReason,
        string $asOf,
    ): array {
        return [
            'status' => 'UNAVAILABLE',
            'as_of' => $asOf,
            'reason_code' => $capability['available']
                ? $fallbackReason
                : $capability['reason'],
            'total' => null,
            'status_counts' => null,
        ];
    }

    private function time(mixed $value): string
    {
        return CarbonImmutable::parse((string) $value)->utc()->toISOString();
    }
}
