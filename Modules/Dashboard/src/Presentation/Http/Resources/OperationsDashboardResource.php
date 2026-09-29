<?php

declare(strict_types=1);

namespace Modules\Dashboard\Presentation\Http\Resources;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Dashboard\Application\Dto\DashboardAttentionDto;
use Modules\Dashboard\Application\Dto\DashboardCapabilityDto;
use Modules\Dashboard\Application\Dto\DashboardShortcutDto;
use Modules\Dashboard\Application\Dto\DashboardUpdateDto;
use Modules\Dashboard\Application\UseCases\GetOperationsDashboard\GetOperationsDashboardResult;
use Modules\Dashboard\Domain\Enums\DashboardReason;
use Modules\Manifest\Domain\Enums\ManifestState;
use Modules\Manifest\Domain\Enums\ManifestTransition;
use Modules\Operations\Domain\Enums\FleetAvailability;

/** @mixin GetOperationsDashboardResult */
final class OperationsDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $capabilities = $this->capabilities;
        $consignments = $this->consignments;
        $manifests = $this->manifests;
        $drivers = $this->drivers;

        return [
            'status' => 'PARTIAL',
            'as_of' => $this->time($this->asOf),
            'node' => ['node_id' => $this->node->node_id, 'node_code' => $this->node->node_code, 'node_title' => $this->node->node_title, 'node_type' => $this->node->node_type],
            'filters' => [
                ['key' => 'OPERATIONAL_DATE', 'value' => null, ...$this->section(DashboardReason::OperationalDateUnsupported)],
                ['key' => 'SHIFT', 'value' => null, ...$this->section(DashboardReason::ShiftUnsupported)],
            ],
            'metrics' => [
                $this->metric('ACTIVE_CONSIGNMENTS', $capabilities->consignment, $consignments?->active),
                $this->metric('OPEN_MANIFESTS', $capabilities->manifest, $manifests?->open),
                $this->metric('AWAITING_NOK_REVIEW', $capabilities->nok, null, DashboardReason::DataNotPersisted),
                $this->metric('AWAITING_NPU_REVIEW', $capabilities->npu, null, DashboardReason::DataNotPersisted),
                $this->metric('OPEN_PICKUP_REQUESTS', $capabilities->pickup, null, DashboardReason::ModuleNotImplemented),
                $this->metric('ACTIVE_DELAYS', $capabilities->consignment, null, DashboardReason::DataNotPersisted),
                $this->metric('SLA_RISK', $capabilities->consignment, null, DashboardReason::DataNotPersisted),
                $this->metric('ACTIVE_DRIVERS', $capabilities->driver, $drivers?->total),
            ],
            'attention' => [...$this->activitySection(false), 'items' => array_map($this->attentionItem(...), $this->attention)],
            'consignments' => [...$this->section($capabilities->consignment->reason), 'total' => $consignments?->total,
                'active' => $consignments?->active, 'status_counts' => $consignments?->statusCounts],
            'pickup_requests' => [...$this->section($capabilities->pickup->reason ?? DashboardReason::ModuleNotImplemented), 'total' => null, 'status_counts' => null],
            'manifests' => [...$this->section($capabilities->manifest->reason), 'total' => $manifests?->total, 'open' => $manifests?->open,
                'failed_rows' => $manifests?->failedRows,
                'state_counts' => $manifests === null ? null : [ManifestState::Draft->value => $manifests->draft, ManifestState::Open->value => $manifests->open, ManifestState::Closed->value => $manifests->closed],
                'target_status_counts' => $manifests === null ? null : [ManifestTransition::Reception->value => $manifests->inbound, ManifestTransition::OutboundConfirmation->value => $manifests->outbound, ManifestTransition::DeliveryAssignment->value => $manifests->delivery]],
            'drivers' => [...$this->section($capabilities->driver->reason), 'total' => $drivers?->total,
                'status_counts' => $drivers === null ? null : [FleetAvailability::Available->value => $drivers->available, FleetAvailability::OnMission->value => $drivers->onMission]],
            'latest_updates' => [...$this->activitySection(true), 'items' => array_map($this->updateItem(...), $this->updates)],
            'shortcuts' => array_map($this->shortcut(...), $this->shortcuts),
        ];
    }

    private function metric(string $key, DashboardCapabilityDto $capability, ?int $value, ?DashboardReason $fallback = null): array
    {
        return ['key' => $key, 'value' => $value, ...$this->section($capability->reason ?? $fallback), 'comparison' => null];
    }

    private function section(?DashboardReason $reason): array
    {
        return ['status' => $reason === null ? 'AVAILABLE' : 'UNAVAILABLE', 'as_of' => $this->time($this->asOf), 'reason_code' => $reason?->value];
    }

    private function activitySection(bool $audit): array
    {
        if ($audit && ! $this->capabilities->audit->available) {
            return $this->section($this->capabilities->audit->reason);
        }
        $availableCount = (int) $this->capabilities->consignment->available + (int) $this->capabilities->manifest->available;
        if ($availableCount === 0) {
            return $this->section($audit ? DashboardReason::NoVisibleAuditTargets : DashboardReason::SectionPermissionFiltered);
        }

        return ['status' => $availableCount === 2 ? 'AVAILABLE' : 'PARTIAL', 'as_of' => $this->time($this->asOf),
            'reason_code' => $availableCount === 2 ? null : DashboardReason::SectionPermissionFiltered->value];
    }

    private function attentionItem(DashboardAttentionDto $item): array
    {
        $manifest = $item->type === 'MANIFEST_VALIDATION_FAILURE';
        $target = $manifest ? '/manifests/'.$item->entityId : '/consignments/'.$item->entityId;
        if ($manifest && $item->actionAvailable) {
            $target .= '/command-station';
        }

        return [
            'type' => $item->type, 'severity' => $item->type === 'CONSIGNMENT_NOK' ? 'HIGH' : 'MEDIUM',
            'title' => match ($item->type) {
                'CONSIGNMENT_NOK' => 'NOK operational status',
                'CONSIGNMENT_NPU' => 'NPU operational status',
                'MANIFEST_VALIDATION_FAILURE' => 'Manifest validation failures',
            },
            'entity_type' => $manifest ? 'MANIFEST' : 'CONSIGNMENT', 'public_identifier' => $item->publicIdentifier,
            'occurred_at' => $this->time($item->occurredAt), 'navigation_target' => $target, 'action_available' => $item->actionAvailable,
        ];
    }

    private function updateItem(DashboardUpdateDto $item): array
    {
        return [
            'audit_id' => $item->auditId, 'action_key' => $item->action,
            'title' => match ($item->action) {
                'CONSIGNMENT_CREATED' => 'Consignment created',
                'CONSIGNMENT_UPDATED' => 'Consignment updated',
                'MANIFEST_CREATED' => 'Manifest created',
                'MANIFEST_CONTEXT_UPDATED' => 'Manifest context updated',
                'MANIFEST_PARCELS_ADDED' => 'Manifest parcels added',
                'MANIFEST_VALIDATED' => 'Manifest validated',
                'MANIFEST_CONFIRMED' => 'Manifest confirmed',
            },
            'entity_type' => $item->entityType, 'public_identifier' => $item->publicIdentifier,
            'occurred_at' => $this->time($item->occurredAt),
            'navigation_target' => ($item->entityType === 'MANIFEST' ? '/manifests/' : '/consignments/').$item->entityId,
        ];
    }

    private function shortcut(DashboardShortcutDto $shortcut): array
    {
        $reason = $shortcut->capability->reason;
        if ($reason === null && ! $shortcut->implemented) {
            $reason = DashboardReason::ModuleNotImplemented;
        }

        return ['key' => $shortcut->key, 'status' => $reason === null ? 'AVAILABLE' : 'UNAVAILABLE',
            'reason_code' => $reason?->value, 'navigation_target' => $reason === null ? $shortcut->target : null];
    }

    private function time(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }
}
