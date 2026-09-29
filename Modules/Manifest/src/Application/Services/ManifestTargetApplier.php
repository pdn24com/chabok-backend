<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Consignment\Application\Contracts\ManifestConsignmentAccessInterface;
use Modules\Consignment\Domain\Enums\CustodyType;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Application\Contracts\ManifestRoutePlannerInterface;
use Modules\Manifest\Application\Contracts\ManifestTargetApplierInterface;
use Modules\Manifest\Application\Dto\ManifestParcelTransitionDto;
use Modules\Manifest\Application\Dto\ManifestRouteEvidenceDto;
use Modules\Manifest\Application\Dto\ManifestRouteReferenceDto;
use Modules\Manifest\Application\Dto\ManifestRouteSelectionDto;
use Modules\Manifest\Application\Dto\ManifestTargetChangeDto;
use Modules\Manifest\Application\Repositories\ManifestParcelRepositoryInterface;
use Modules\Manifest\Domain\Enums\ManifestTransition;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestParcelRecord;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;
use Modules\Operations\Application\Contracts\ManifestRouteAccessInterface;
use Modules\Operations\Application\Contracts\ManifestTaskAccessInterface;
use Modules\Operations\Application\Contracts\ManifestTaskBatchWriterInterface;
use Modules\Operations\Application\UseCases\ActivateManifestDelivery\ActivateManifestDeliveryCommand;
use Modules\Operations\Application\UseCases\ActivateManifestDelivery\ActivateManifestDeliveryHandler;
use Modules\Operations\Application\UseCases\EnsurePendingDelivery\EnsurePendingDeliveryCommand;
use Modules\Operations\Application\UseCases\EnsurePendingDelivery\EnsurePendingDeliveryHandler;
use Modules\Operations\Domain\Enums\RoutePlanLegStatus;
use Modules\Operations\Domain\Enums\RoutePlanStatus;

final readonly class ManifestTargetApplier implements ManifestTargetApplierInterface
{
    public function __construct(
        private ManifestRoutePlannerInterface $manifestRoutePlanner,
        private ManifestRouteAccessInterface $manifestRouteAccess,
        private ClockInterface $clock,
        private ManifestConsignmentAccessInterface $manifestConsignmentAccess,
        private EnsurePendingDeliveryHandler $ensurePendingDeliveryHandler,
        private ActivateManifestDeliveryHandler $activateManifestDeliveryHandler,
        private ManifestTaskAccessInterface $manifestTaskAccess,
        private ManifestTaskBatchWriterInterface $manifestTaskBatchWriter,
        private ManifestParcelRepositoryInterface $manifestParcelRepository,
    ) {}

    /** @param list<ParcelRecord> $parcels @return array<string, ManifestParcelTransitionDto> */
    public function applyTargets(AuthenticatedPrincipal $actor, string $node, ManifestRecord $manifest, array $parcels, string $correlationId): array
    {
        $target = ManifestTransition::tryFrom((string) $manifest->manifest_status);
        $transitions = [];
        $updates = new Collection;
        $routes = [];
        $departures = [];
        $handledDeliveries = [];
        $receptions = [];
        $deliveryNodes = $target === ManifestTransition::Reception ? $this->manifestConsignmentAccess->deliveryNodes($actor->hqId, array_map(static fn (ParcelRecord $parcel): string => $parcel->consignment_id, $parcels)) : [];
        $sources = in_array($target, [ManifestTransition::Reception, ManifestTransition::TransitUnload], true)
            ? $this->manifestParcelRepository->succeededSourceRows($actor->hqId, $manifest->source_manifest_id,
                array_map(static fn (ParcelRecord $parcel): string => $parcel->parcel_id, $parcels))
            : new Collection;
        if ($parcels !== [] && $target === ManifestTransition::OutboundConfirmation) {
            $this->manifestRouteAccess->confirmOutboundLeg($actor->hqId, $manifest->route_plan_leg_id, ['status' => RoutePlanLegStatus::OutboundConfirmed->value, 'updated_at' => $this->clock->now()]);
        }
        foreach ($parcels as $parcel) {
            $change = $this->applyTarget($actor, $node, $manifest, $parcel, $correlationId, $routes, $departures, $handledDeliveries, $sources, $receptions, $deliveryNodes);
            $updates->push($change->updatedParcel);
            $transitions[$parcel->parcel_id] = new ManifestParcelTransitionDto($parcel, $change->evidence);
        }
        $this->manifestConsignmentAccess->saveTenantParcels($actor->hqId, $updates);
        $this->manifestTaskBatchWriter->apply($actor->hqId, $node, $manifest->manifest_status, $manifest->assigned_driver_id, $parcels);

        return $transitions;
    }

    private function applyTarget(
        AuthenticatedPrincipal $actor,
        string $node,
        ManifestRecord $m,
        ParcelRecord $p,
        string $correlationId,
        array &$routes, array &$departures, array &$handledDeliveries, Collection $sources, array &$receptions, array $deliveryNodes,
    ): ManifestTargetChangeDto {
        $target = ManifestTransition::tryFrom((string) $m->manifest_status);
        $selection = $this->route($actor, $node, $m, $p, $correlationId, $routes, $departures, $sources, $receptions);
        $route = $selection->evidence;
        $activeRoutePlanId = $selection->activePlanId;
        $activeRouteLegId = $selection->activeLegId;
        $custody = match ($target) {
            ManifestTransition::PickupAssignment, ManifestTransition::PickupCompletion, ManifestTransition::PickupException => CustodyType::PickupDriver,
            ManifestTransition::LinehaulDeparture => CustodyType::LinehaulDriver,
            ManifestTransition::DeliveryAssignment, ManifestTransition::DeliveryException => CustodyType::DeliveryDriver,
            ManifestTransition::DeliveryCompletion => CustodyType::Recipient,
            default => CustodyType::Node,
        };
        $nextNode = $custody->restsAtNode() ? $node : null;
        $updated = clone $p;
        $updated->forceFill([
            'current_status' => (string) $m->manifest_status,
            'current_node_id' => $nextNode,
            'current_custody_type' => $custody->value,
            'current_custodian_id' => $custody->custodian($m->assigned_driver_id, $node),
            'active_route_plan_id' => $activeRoutePlanId,
            'active_route_plan_leg_id' => $activeRouteLegId,
            'version' => (int) $p->version + 1,
            'updated_at' => $this->clock->now(),
        ]);
        if (! isset($handledDeliveries[$p->consignment_id])) {
            $needsDelivery = $target === ManifestTransition::DeliveryAssignment
                || $target === ManifestTransition::Reception && ($p->current_status === ManifestTransition::LinehaulDeparture->value
                    || $p->current_status === ManifestTransition::PickupCompletion->value && ($deliveryNodes[$p->consignment_id] ?? null) === $node);
            if ($needsDelivery) {
                // A task owns its assignment locks, eligibility checks and immutable history.
                // Resolve each parent once; distinct parents deliberately retain their own use case.
                $this->ensurePendingDeliveryHandler->handle(new EnsurePendingDeliveryCommand($actor, $node, $p->consignment_id));
                if ($target === ManifestTransition::DeliveryAssignment) {
                    $this->activateManifestDeliveryHandler->handle(new ActivateManifestDeliveryCommand($actor, $node, $p->consignment_id, $m->assigned_driver_id, $m->manifest_id));
                }
                $handledDeliveries[$p->consignment_id] = true;
            }
        }

        return new ManifestTargetChangeDto($updated, new ManifestRouteEvidenceDto(
            originNodeId: $m->origin_node_id ?? $p->current_node_id,
            destinationNodeId: $m->destination_node_id ?? $nextNode,
            routePlanId: $route->planId,
            routeDefinitionVersionId: $route->definitionVersionId,
            routePlanLegId: $route->legId,
            routeDefinitionVersionLegId: $route->definitionVersionLegId,
            assignedDriverId: $m->assigned_driver_id,
            assignedVehicleId: $m->assigned_vehicle_id,
        ));
    }

    /** Route guards run once per plan/leg; a manifest may contain independent routes. */
    private function route(AuthenticatedPrincipal $actor, string $node, ManifestRecord $m, ParcelRecord $p, string $correlationId,
        array &$routes, array &$departures, Collection $sources, array &$receptions): ManifestRouteSelectionDto
    {
        $target = ManifestTransition::tryFrom((string) $m->manifest_status);
        $route = new ManifestRouteReferenceDto($m->route_plan_id, $m->route_definition_version_id, $m->route_plan_leg_id, $m->route_definition_version_leg_id);
        $activeRoutePlanId = $route->planId ?? $p->active_route_plan_id;
        $activeRouteLegId = $route->legId ?? $p->active_route_plan_leg_id;
        if ($target === ManifestTransition::RouteRegistration) {
            $route = $routes[$p->consignment_id] ??= $this->manifestRoutePlanner->ensureRoute($actor, $node, $p, $correlationId);
            $activeRoutePlanId = $route->planId;
            $activeRouteLegId = $route->legId;
        }
        if ($target === ManifestTransition::LinehaulDeparture) {
            $leg = $departures[$p->active_route_plan_leg_id] ??= $this->manifestRouteAccess->lockDepartureLeg($actor->hqId, $p->active_route_plan_leg_id);
            if ($leg === null) {
                throw new ApiException(ApiErrorCode::RouteLegNotReady, 422, 'manifest.route_leg_is_not_ready_departure');
            }
            $route = new ManifestRouteReferenceDto($leg->route_plan_id, $leg->plan->route_definition_version_id, $leg->route_plan_leg_id, $leg->source_route_definition_version_leg_id);
            $activeRoutePlanId = $route->planId;
            $activeRouteLegId = $route->legId;
            if ((string) $leg->status === RoutePlanLegStatus::OutboundConfirmed->value) {
                $this->manifestRouteAccess->updateTenantLeg($actor->hqId, $leg->route_plan_leg_id, ['status' => RoutePlanLegStatus::InTransit->value, 'updated_at' => $this->clock->now()]);
                $leg->status = RoutePlanLegStatus::InTransit->value;
            }
        }
        if (in_array($target, [ManifestTransition::Reception, ManifestTransition::TransitUnload], true) && $p->current_status === ManifestTransition::LinehaulDeparture->value) {
            $source = $sources->get($p->parcel_id);
            if ($source === null) {
                throw new ApiException(ApiErrorCode::RouteLegUnavailable, 422, 'manifest.source_movement_evidence_is_unavailable');
            }
            $route = new ManifestRouteReferenceDto($source->route_plan_id, $source->route_definition_version_id, $source->route_plan_leg_id, $source->route_definition_version_leg_id);
            $received = $receptions[$source->route_plan_leg_id] ??= $this->receiveRoute($actor, $node, $target, $source);
            $activeRoutePlanId = $received->activePlanId;
            $activeRouteLegId = $received->activeLegId;
        }
        if ($target === ManifestTransition::OutboundConfirmation) {
            $activeRoutePlanId = $m->route_plan_id;
            $activeRouteLegId = $m->route_plan_leg_id;
        }

        return new ManifestRouteSelectionDto($route, $activeRoutePlanId, $activeRouteLegId);
    }

    private function receiveRoute(AuthenticatedPrincipal $actor, string $node, ManifestTransition $target, ManifestParcelRecord $source): ManifestRouteSelectionDto
    {
        $route = new ManifestRouteReferenceDto($source->route_plan_id, $source->route_definition_version_id, $source->route_plan_leg_id, $source->route_definition_version_leg_id);
        $currentLeg = $this->manifestRouteAccess->lockReceptionLeg($actor->hqId, $source->route_plan_leg_id, $source->route_plan_id);
        if ($currentLeg === null) {
            throw new ApiException(ApiErrorCode::RouteLegNotReady, 422, 'manifest.source_route_leg_is_unavailable');
        }
        $this->manifestRouteAccess->updateTenantLeg($actor->hqId, $source->route_plan_leg_id, [
            'status' => RoutePlanLegStatus::Received->value,
            'received_at' => $this->clock->now(),
            'updated_at' => $this->clock->now(),
        ]);
        $next = $this->manifestRouteAccess->followingLeg($actor->hqId, $source->route_plan_id, $currentLeg->leg_order, $node);
        if ($target === ManifestTransition::TransitUnload) {
            if ($next === null) {
                throw new ApiException(ApiErrorCode::RouteLegUnavailable, 422, 'manifest.no_following_published_route_leg_is_available');
            }
            $activeRoutePlanId = $source->route_plan_id;
            $activeRouteLegId = $next->route_plan_leg_id;
        } else {
            $activeRoutePlanId = $source->route_plan_id;
            $activeRouteLegId = null;
            $hasUnreceivedLeg = $this->manifestRouteAccess->hasUnreceivedLeg($actor->hqId, $source->route_plan_id);
            if (! $hasUnreceivedLeg) {
                $this->manifestRouteAccess->updateTenantPlan($actor->hqId, $source->route_plan_id, [
                    'status' => RoutePlanStatus::Completed->value,
                    'active_slot' => null,
                    'updated_at' => $this->clock->now(),
                ]);
            }
        }

        return new ManifestRouteSelectionDto($route, $activeRoutePlanId, $activeRouteLegId);
    }
}
