<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Modules\Manifest\Application\Contracts\ManifestContextProjectionInterface;
use Modules\Manifest\Application\Dto\ManifestContextReferenceSetDto;
use Modules\Manifest\Application\Dto\ManifestContextSummaryDto;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;
use Modules\Operations\Application\Contracts\ManifestDirectoryReaderInterface;
use Modules\Operations\Application\Contracts\ManifestRouteAccessInterface;
use Modules\Operations\Application\Contracts\ManifestTaskAccessInterface;

final readonly class ManifestContextProjection implements ManifestContextProjectionInterface
{
    public function __construct(
        private ManifestDirectoryReaderInterface $manifestDirectoryReader,
        private ManifestTaskAccessInterface $manifestTaskAccess,
        private ManifestRouteAccessInterface $manifestRouteAccess,
    ) {}

    /** @param list<ManifestRecord> $manifests @return array<string, ManifestContextSummaryDto> */
    public function summaries(string $hq, array $manifests): array
    {
        if ($manifests === []) {
            return [];
        }
        $nodeIds = $driverIds = $vehicleIds = $planIds = $legIds = [];
        foreach ($manifests as $manifest) {
            $nodeIds[] = $manifest->node_id;
            $nodeIds[] = $manifest->origin_node_id;
            $nodeIds[] = $manifest->destination_node_id;
            $driverIds[] = $manifest->assigned_driver_id;
            $vehicleIds[] = $manifest->assigned_vehicle_id;
            $planIds[] = $manifest->route_plan_id;
            $legIds[] = $manifest->route_plan_leg_id;
        }
        $references = new ManifestContextReferenceSetDto(
            $this->manifestDirectoryReader->nodesByIds($this->ids($nodeIds), $hq)->keyBy('node_id'),
            $this->manifestTaskAccess->driversByIds($this->ids($driverIds), $hq)->keyBy('driver_id'),
            $this->manifestTaskAccess->vehiclesByIds($this->ids($vehicleIds), $hq)->keyBy('vehicle_id'),
            $this->manifestRouteAccess->plansByIds($this->ids($planIds), $hq)->keyBy('route_plan_id'),
            $this->manifestRouteAccess->legsByIds($this->ids($legIds), $hq)->keyBy('route_plan_leg_id'),
        );
        $summaries = [];
        foreach ($manifests as $manifest) {
            $summaries[$manifest->manifest_id] = $this->summary($manifest, $references);
        }

        return $summaries;
    }

    /** @param list<string|null> $values @return list<string> */
    private function ids(array $values): array
    {
        return array_values(array_unique(array_filter($values, static fn (?string $id): bool => $id !== null && $id !== '')));
    }

    private function summary(ManifestRecord $manifest, ManifestContextReferenceSetDto $references): ManifestContextSummaryDto
    {
        $target = $manifest->manifest_status;
        $inbound = in_array($target, ['IR', 'CI'], true);
        $outbound = in_array($target, ['OF', 'OS'], true);
        $relatedNodeId = $inbound ? $manifest->origin_node_id : ($outbound ? $manifest->destination_node_id : $manifest->destination_node_id ?? $manifest->origin_node_id);

        return new ManifestContextSummaryDto($manifest->operational_context_type, $references->nodes->get($manifest->node_id),
            $references->nodes->get($manifest->destination_node_id), $references->nodes->get($manifest->node_id),
            $references->nodes->get($relatedNodeId), $inbound ? 'SOURCE' : ($outbound ? 'DESTINATION' : 'COUNTERPARTY'),
            $references->nodes->get($manifest->origin_node_id), $references->nodes->get($manifest->destination_node_id),
            $references->plans->get($manifest->route_plan_id), $references->legs->get($manifest->route_plan_leg_id),
            $references->drivers->get($manifest->assigned_driver_id), $references->vehicles->get($manifest->assigned_vehicle_id));
    }
}
