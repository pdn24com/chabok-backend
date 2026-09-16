<?php

declare(strict_types=1);

namespace Modules\Dashboard\Application\UseCases\GetOperationsDashboard;

use Modules\Dashboard\Domain\DashboardMetricDefinitions;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetOperationsDashboardHandler
{
    private const LIMIT = 6;

    public function __construct(
        private \Modules\Dashboard\Application\Repositories\DashboardRepository $dashboard,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Foundation\Application\ScopedAccess $scopedAccess,
        private AuthorizationContextResolver $authorization,
    )
    {
    }
    /** @return array<string, mixed> */

    public function handle(GetOperationsDashboardCommand $command): GetOperationsDashboardResult
    {
        return new GetOperationsDashboardResult($this->execute($command->actor, $command->nodeId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        $context = $this->authorization->resolve($actor);
        $context['acting_node_id'] = $nodeId;
        $this->assertDashboardAccess($context, $nodeId);
        $node = $this->dashboard->activeNode($actor->hqId, $nodeId);
        if ($node === null) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
        }
        $asOf = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
        $consignmentCapability = $this->capability($context, 'Consignment', 'consignment.view');
        $manifestCapability = $this->capability($context, 'Manifest', 'manifest.view');
        $pickupCapability = $this->capability($context, 'Pickup', 'pickup_request.view');
        $driverCapability = $this->capability($context, 'Driver', 'fleet.driver.view');
        $nokCapability = $this->capability($context, 'Exception', 'exception.nok.view');
        $npuCapability = $this->capability($context, 'Exception', 'exception.npu.view');
        $consignments = $this->consignmentSummary($actor->hqId, $nodeId, $asOf, $consignmentCapability);
        $manifests = $this->manifestSummary($actor->hqId, $nodeId, $asOf, $manifestCapability);
        $drivers = $this->driverSummary($actor->hqId, $nodeId, $asOf, $driverCapability);
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
                $this->unavailableFilter('OPERATIONAL_DATE', 'OPERATIONAL_DATE_FILTER_UNSUPPORTED', $asOf),
                $this->unavailableFilter('SHIFT', 'SHIFT_FILTER_UNSUPPORTED', $asOf),
            ],
            'metrics' => [
                $this->metric('ACTIVE_CONSIGNMENTS', $consignmentCapability, $consignments['active'], $asOf),
                $this->metric('OPEN_MANIFESTS', $manifestCapability, $manifests['open'], $asOf),
                $this->unavailableMetric('AWAITING_NOK_REVIEW', $nokCapability, 'DATA_NOT_PERSISTED', $asOf),
                $this->unavailableMetric('AWAITING_NPU_REVIEW', $npuCapability, 'DATA_NOT_PERSISTED', $asOf),
                $this->unavailableMetric('OPEN_PICKUP_REQUESTS', $pickupCapability, 'MODULE_NOT_IMPLEMENTED', $asOf),
                $this->unavailableMetric('ACTIVE_DELAYS', $consignmentCapability, 'DATA_NOT_PERSISTED', $asOf),
                $this->unavailableMetric('SLA_RISK', $consignmentCapability, 'DATA_NOT_PERSISTED', $asOf),
                $this->metric('ACTIVE_DRIVERS', $driverCapability, $drivers['total'], $asOf),
            ],
            'attention' => $this->attention($actor->hqId, $nodeId, $context, $asOf, $consignmentCapability, $manifestCapability),
            'consignments' => $consignments,
            'pickup_requests' => $this->unavailableDomain($pickupCapability, 'MODULE_NOT_IMPLEMENTED', $asOf),
            'manifests' => $manifests,
            'drivers' => $drivers,
            'latest_updates' => $this->latestUpdates($actor->hqId, $nodeId, $context, $asOf, $consignmentCapability, $manifestCapability),
            'shortcuts' => $this->shortcuts($context),
        ];
    }
    /** @param array<string, mixed> $context */

    private function assertDashboardAccess(array $context, string $nodeId): void
    {
        $foundation = $this->capability($context, 'Foundation', 'branch_panel.access');
        if (!$foundation['available']) {
            $code = $foundation['reason'] === 'ENTITLEMENT_DISABLED' ? ApiErrorCode::EntitlementDisabled : ApiErrorCode::PermissionDenied;
            throw new ApiException($code, 403, 'Access denied.');
        }
        if (!in_array($nodeId, $this->scopedAccess->nodes($context, 'branch_panel.access'), true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
        }
    }
    /**
     * @param array<string, mixed> $context
     * @return array{available: bool, reason: string|null}
     */

    private function capability(array $context, string $moduleCode, string $permission): array
    {
        $enabled = array_filter($context['module_entitlements'], fn(array $item): bool => strcasecmp((string) $item['module_code'], $moduleCode) === 0 && $item['status'] === 'ENABLED') !== [];
        if (!$enabled) {
            return ['available' => false, 'reason' => 'ENTITLEMENT_DISABLED'];
        }
        if (!in_array($permission, $context['permissions'], true) || !in_array($context['acting_node_id'] ?? '', $this->scopedAccess->nodes($context, $permission), true)) {
            return ['available' => false, 'reason' => 'PERMISSION_DENIED'];
        }
        return ['available' => true, 'reason' => null];
    }
    /**
     * @param array{available: bool, reason: string|null} $capability
     * @return array<string, mixed>
     */

    private function consignmentSummary(string $hqId, string $nodeId, string $asOf, array $capability): array
    {
        if (!$capability['available']) {
            return [
                'status' => 'UNAVAILABLE',
                'as_of' => $asOf,
                'reason_code' => $capability['reason'],
                'total' => null,
                'active' => null,
                'status_counts' => null,
            ];
        }
        $row = (array) $this->dashboard->consignmentCounts($hqId, $nodeId);
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

    private function manifestSummary(string $hqId, string $nodeId, string $asOf, array $capability): array
    {
        if (!$capability['available']) {
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
        $row = (array) $this->dashboard->manifestCounts($hqId, $nodeId);
        $failedRows = $this->dashboard->failedManifestRowCount($hqId, $nodeId);
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

    private function driverSummary(string $hqId, string $nodeId, string $asOf, array $capability): array
    {
        if (!$capability['available']) {
            return [
                'status' => 'UNAVAILABLE',
                'as_of' => $asOf,
                'reason_code' => $capability['reason'],
                'total' => null,
                'status_counts' => null,
            ];
        }
        $row = (array) $this->dashboard->driverCounts($hqId, $nodeId);
        return [
            'status' => 'AVAILABLE',
            'as_of' => $asOf,
            'reason_code' => null,
            'total' => (int) ($row['total'] ?? 0),
            'status_counts' => ['AVAILABLE' => (int) ($row['status_AVAILABLE'] ?? 0), 'ON_MISSION' => (int) ($row['status_ON_MISSION'] ?? 0)],
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
    ): array
    {
        $items = [];
        if ($consignmentCapability['available']) {
            $canAct = in_array('consignment.edit', $context['permissions'], true);
            $rows = $this->dashboard->exceptionConsignments($hqId, $nodeId, self::LIMIT);
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
            $rows = $this->dashboard->failedManifests($hqId, $nodeId, self::LIMIT);
            foreach ($rows as $row) {
                $target = $canEdit ? "/manifests/{$row->manifest_id}/command-station" : "/manifests/{$row->manifest_id}";
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
        usort($items, static fn(array $left, array $right): int => [$right['_priority'], $right['occurred_at']] <=> [$left['_priority'], $left['occurred_at']]);
        $items = array_slice($items, 0, self::LIMIT);
        $items = array_map(static function (array $item): array {
            unset($item['_priority']);
            return $item;
        }, $items);
        $availableCount = (int) $consignmentCapability['available'] + (int) $manifestCapability['available'];
        if ($availableCount === 0) {
            return ['status' => 'UNAVAILABLE', 'as_of' => $asOf, 'reason_code' => 'SECTION_PERMISSION_FILTERED', 'items' => []];
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
    ): array
    {
        $auditCapability = $this->capability($context, 'Audit', 'audit.view');
        if (!$auditCapability['available']) {
            return ['status' => 'UNAVAILABLE', 'as_of' => $asOf, 'reason_code' => $auditCapability['reason'], 'items' => []];
        }
        if (!$consignmentCapability['available'] && !$manifestCapability['available']) {
            return ['status' => 'UNAVAILABLE', 'as_of' => $asOf, 'reason_code' => 'NO_VISIBLE_AUDIT_TARGETS', 'items' => []];
        }
        $items = [];
        if ($consignmentCapability['available']) {
            $rows = $this->dashboard->consignmentUpdates($hqId, $nodeId, self::LIMIT);
            foreach ($rows as $row) {
                $items[] = [
                    'audit_id' => (string) $row->audit_id,
                    'action_key' => (string) $row->action_key,
                    'title' => $row->action_key === 'CONSIGNMENT_CREATED' ? 'Consignment created' : 'Consignment updated',
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
            $rows = $this->dashboard->manifestUpdates($hqId, $nodeId, self::LIMIT, array_keys($titles));
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
        usort($items, static fn(array $left, array $right): int => $right['occurred_at'] <=> $left['occurred_at']);
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
            if (!$capability['available']) {
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

    private function metric(string $key, array $capability, mixed $value, string $asOf): array
    {
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

    private function unavailableMetric(string $key, array $capability, string $fallbackReason, string $asOf): array
    {
        return [
            'key' => $key,
            'value' => null,
            'status' => 'UNAVAILABLE',
            'as_of' => $asOf,
            'reason_code' => $capability['available'] ? $fallbackReason : $capability['reason'],
            'comparison' => null,
        ];
    }
    /** @return array<string, mixed> */

    private function unavailableFilter(string $key, string $reason, string $asOf): array
    {
        return ['key' => $key, 'value' => null, 'status' => 'UNAVAILABLE', 'as_of' => $asOf, 'reason_code' => $reason];
    }
    /**
     * @param array{available: bool, reason: string|null} $capability
     * @return array<string, mixed>
     */

    private function unavailableDomain(array $capability, string $fallbackReason, string $asOf): array
    {
        return [
            'status' => 'UNAVAILABLE',
            'as_of' => $asOf,
            'reason_code' => $capability['available'] ? $fallbackReason : $capability['reason'],
            'total' => null,
            'status_counts' => null,
        ];
    }

    private function time(mixed $value): string
    {
        return (new \DateTimeImmutable((string) $value))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }
}
