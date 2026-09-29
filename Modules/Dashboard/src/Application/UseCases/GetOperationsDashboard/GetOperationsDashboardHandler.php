<?php

declare(strict_types=1);

namespace Modules\Dashboard\Application\UseCases\GetOperationsDashboard;

use Carbon\CarbonImmutable;
use Modules\Dashboard\Application\Dto\DashboardAttentionDto;
use Modules\Dashboard\Application\Dto\DashboardCapabilitiesDto;
use Modules\Dashboard\Application\Dto\DashboardCapabilityDto;
use Modules\Dashboard\Application\Dto\DashboardShortcutDto;
use Modules\Dashboard\Application\Dto\DashboardUpdateDto;
use Modules\Dashboard\Application\Repositories\DashboardRepositoryInterface;
use Modules\Dashboard\Domain\Enums\DashboardReason;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Contracts\ScopedAccessInterface;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\ScopeCoverage;
use Modules\Organization\Application\Repositories\NodeRepositoryInterface;

final readonly class GetOperationsDashboardHandler
{
    private const LIMIT = 6;

    public function __construct(
        private DashboardRepositoryInterface $dashboardRepository,
        private ClockInterface $clock,
        private ScopedAccessInterface $scopedAccess,
        private AccessContextResolverInterface $accessContextResolver,
        private NodeRepositoryInterface $nodeRepository,
    ) {}

    public function handle(GetOperationsDashboardCommand $command): GetOperationsDashboardResult
    {
        $hqId = $command->actor->hqId;
        if ($hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
        $context = $this->accessContextResolver->resolve($command->actor)->atNode($command->nodeId);
        $this->assertDashboardAccess($context, $command->nodeId);
        $node = $this->nodeRepository->findActive($hqId, $command->nodeId);
        if ($node === null) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'common.access_denied');
        }
        // The selected node is active and tenant-owned. Reuse one topology snapshot
        // for every section and shortcut instead of querying scopes for each card.
        $coverage = $this->scopedAccess->coverage($hqId);
        $capabilities = new DashboardCapabilitiesDto(
            consignment: $this->capability($context, $coverage, 'Consignment', 'consignment.view'),
            manifest: $this->capability($context, $coverage, 'Manifest', 'manifest.view'),
            pickup: $this->capability($context, $coverage, 'Pickup', 'pickup_request.view'),
            driver: $this->capability($context, $coverage, 'Driver', 'fleet.driver.view'),
            nok: $this->capability($context, $coverage, 'Exception', 'exception.nok.view'),
            npu: $this->capability($context, $coverage, 'Exception', 'exception.npu.view'),
            audit: $this->capability($context, $coverage, 'Audit', 'audit.view'),
        );

        return new GetOperationsDashboardResult(
            asOf: $this->clock->now(),
            node: $node,
            capabilities: $capabilities,
            consignments: $capabilities->consignment->available ? $this->dashboardRepository->consignmentCounts($hqId, $command->nodeId) : null,
            manifests: $capabilities->manifest->available ? $this->dashboardRepository->manifestCounts($hqId, $command->nodeId) : null,
            drivers: $capabilities->driver->available ? $this->dashboardRepository->driverCounts($hqId, $command->nodeId) : null,
            attention: $this->attention($hqId, $command->nodeId, $context, $capabilities),
            updates: $this->updates($hqId, $command->nodeId, $capabilities),
            shortcuts: $this->shortcuts($context, $coverage),
        );
    }

    private function assertDashboardAccess(AccessContextDto $context, string $nodeId): void
    {
        if (! $context->isModuleEnabled('Foundation', true)) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'common.access_denied');
        }
        if (! $context->hasPermission('branch_panel.access') || ! in_array($nodeId, $this->scopedAccess->nodes($context, 'branch_panel.access'), true)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'common.access_denied');
        }
    }

    private function capability(AccessContextDto $context, ScopeCoverage $coverage, string $module, string $permission): DashboardCapabilityDto
    {
        if (! $context->isModuleEnabled($module, true)) {
            return new DashboardCapabilityDto(DashboardReason::EntitlementDisabled);
        }
        if (! $context->hasPermission($permission) || ! $coverage->covers($context->scopesFor($permission), ScopeType::NODE, $context->actingNodeId)) {
            return new DashboardCapabilityDto(DashboardReason::PermissionDenied);
        }

        return new DashboardCapabilityDto;
    }

    /** @return list<DashboardAttentionDto> */
    private function attention(string $hqId, string $nodeId, AccessContextDto $context, DashboardCapabilitiesDto $capabilities): array
    {
        $items = [];
        if ($capabilities->consignment->available) {
            foreach ($this->dashboardRepository->exceptionConsignments($hqId, $nodeId, self::LIMIT) as $row) {
                $items[] = new DashboardAttentionDto('CONSIGNMENT_'.$row->current_status, $row->consignment_id, $row->consignment_number,
                    CarbonImmutable::parse($row->updated_at)->toDateTimeImmutable(), $context->hasPermission('consignment.edit'));
            }
        }
        if ($capabilities->manifest->available) {
            foreach ($this->dashboardRepository->failedManifests($hqId, $nodeId, self::LIMIT) as $row) {
                $items[] = new DashboardAttentionDto('MANIFEST_VALIDATION_FAILURE', $row->manifest_id, $row->manifest_number,
                    CarbonImmutable::parse($row->updated_at)->toDateTimeImmutable(), $context->hasPermission('manifest.edit'));
            }
        }
        usort($items, static fn (DashboardAttentionDto $left, DashboardAttentionDto $right): int => [$right->type === 'CONSIGNMENT_NOK', $right->occurredAt] <=> [$left->type === 'CONSIGNMENT_NOK', $left->occurredAt]);

        return array_slice($items, 0, self::LIMIT);
    }

    /** @return list<DashboardUpdateDto> */
    private function updates(string $hqId, string $nodeId, DashboardCapabilitiesDto $capabilities): array
    {
        if (! $capabilities->audit->available) {
            return [];
        }
        $items = [];
        if ($capabilities->consignment->available) {
            $items = $this->dashboardRepository->consignmentUpdates($hqId, $nodeId, self::LIMIT);
        }
        if ($capabilities->manifest->available) {
            $items = [...$items, ...$this->dashboardRepository->manifestUpdates($hqId, $nodeId, self::LIMIT)];
        }
        usort($items, static fn (DashboardUpdateDto $left, DashboardUpdateDto $right): int => $right->occurredAt <=> $left->occurredAt);

        return array_slice($items, 0, self::LIMIT);
    }

    /** @return list<DashboardShortcutDto> */
    private function shortcuts(AccessContextDto $context, ScopeCoverage $coverage): array
    {
        return [
            new DashboardShortcutDto('CONSIGNMENTS', $this->capability($context, $coverage, 'Consignment', 'consignment.view'), '/consignments', true),
            new DashboardShortcutDto('CREATE_CONSIGNMENT', $this->capability($context, $coverage, 'Consignment', 'consignment.create'), '/consignments/new', true),
            new DashboardShortcutDto('MANIFESTS', $this->capability($context, $coverage, 'Manifest', 'manifest.view'), '/manifests', true),
            new DashboardShortcutDto('CREATE_MANIFEST', $this->capability($context, $coverage, 'Manifest', 'manifest.create'), '/manifests/new', true),
            new DashboardShortcutDto('PICKUP_REQUESTS', $this->capability($context, $coverage, 'Pickup', 'pickup_request.view'), null, false),
            new DashboardShortcutDto('DRIVERS', $this->capability($context, $coverage, 'Driver', 'fleet.driver.view'), '/app/administration/fleet/drivers', true),
            new DashboardShortcutDto('LIVE_OPERATIONS', $this->capability($context, $coverage, 'LiveOperations', 'live_operations.view'), null, false),
            new DashboardShortcutDto('EXCEPTIONS', $this->capability($context, $coverage, 'Exception', 'exception.nok.view'), null, false),
        ];
    }
}
