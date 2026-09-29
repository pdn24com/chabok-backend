<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Repositories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Modules\Operations\Application\Dto\RouteDefinitionFiltersDto;
use Modules\Operations\Application\Repositories\RouteDefinitionRepositoryInterface;
use Modules\Operations\Domain\Enums\ConfigVersionStatus;
use Modules\Operations\Domain\Enums\RouteDefinitionLegStatus;
use Modules\Operations\Domain\Enums\RoutePurpose;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionLegRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionLegRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionRecord;

final class EloquentRouteDefinitionRepository implements RouteDefinitionRepositoryInterface
{
    public function definitionExists(?string $hqId, string $definitionId): bool
    {
        return RouteDefinitionRecord::query()->where(['hq_id' => $hqId, 'route_definition_id' => $definitionId])->exists();
    }

    public function codeTaken(?string $hqId, string $code): bool
    {
        return RouteDefinitionRecord::query()->where(['hq_id' => $hqId, 'route_code' => $code])->exists();
    }

    public function findDefinition(?string $hqId, string $definitionId): ?RouteDefinitionRecord
    {
        return RouteDefinitionRecord::query()->where(['hq_id' => $hqId, 'route_definition_id' => $definitionId])->first();
    }

    public function paginateDefinitions(?string $hqId, RouteDefinitionFiltersDto $filters): LengthAwarePaginator
    {
        $query = RouteDefinitionRecord::query()->where('hq_id', $hqId);
        if ($filters->search !== null) {
            $query->where(fn ($match) => $match->where('route_code', 'like', '%'.$filters->search.'%')->orWhere('route_title', 'like', '%'.$filters->search.'%'));
        }
        if ($filters->purpose !== null) {
            $query->whereHas('versions', fn ($versions) => $versions->where('purpose', $filters->purpose));
        }

        return $query->orderBy('route_code')->paginate((int) $filters->perPage, ['*'], 'page', (int) $filters->page);
    }

    public function activeDefinitionsWithLegs(?string $hqId): Collection
    {
        return RouteDefinitionRecord::query()->where(['hq_id' => $hqId, 'status' => 'ACTIVE'])
            ->with(['legs' => fn ($legs) => $legs->where('status', RouteDefinitionLegStatus::Active->value)
                ->whereHas('originNode')->whereHas('destinationNode')->with(['originNode', 'destinationNode'])])
            ->orderBy('route_code')->get();
    }

    public function publishVersion(string $definitionId, array $changes): void
    {
        RouteDefinitionRecord::query()->where('route_definition_id', $definitionId)->increment('version', 1, $changes);
    }

    public function retirePublishedVersion(string $definitionId, string $versionId, array $changes): void
    {
        RouteDefinitionRecord::query()->where(['route_definition_id' => $definitionId, 'published_version_id' => $versionId])
            ->increment('version', 1, $changes);
    }

    public function findVersionWithLegs(?string $hqId, string $versionId): ?RouteDefinitionVersionRecord
    {
        return RouteDefinitionVersionRecord::query()->where(['hq_id' => $hqId, 'route_definition_version_id' => $versionId])->with('legs')->first();
    }

    public function findDefinitionVersionWithLegs(?string $hqId, string $definitionId, string $versionId): ?RouteDefinitionVersionRecord
    {
        return $this->definitionVersion($hqId, $definitionId, $versionId)->first();
    }

    public function lockDefinitionVersionWithLegs(?string $hqId, string $definitionId, string $versionId): ?RouteDefinitionVersionRecord
    {
        return $this->definitionVersion($hqId, $definitionId, $versionId)->lockForUpdate()->first();
    }

    public function paginateVersions(?string $hqId, string $definitionId, int $page, int $perPage): LengthAwarePaginator
    {
        return RouteDefinitionVersionRecord::query()->where(['hq_id' => $hqId, 'route_definition_id' => $definitionId])
            ->with('legs')->orderByDesc('version_number')->paginate($perPage, ['*'], 'page', $page);
    }

    public function nextVersionNumber(string $definitionId): int
    {
        return (int) RouteDefinitionVersionRecord::query()->where('route_definition_id', $definitionId)->max('version_number') + 1;
    }

    public function effectiveVersionsForLane(string $hqId, RoutePurpose $purpose, string $originNodeId, string $destinationNodeId, ?string $offeringVersionId, DateTimeInterface $at): Collection
    {
        return RouteDefinitionVersionRecord::query()->where([
            'hq_id' => $hqId, 'status' => ConfigVersionStatus::Published->value, 'purpose' => $purpose->value,
            'origin_node_id' => $originNodeId, 'destination_node_id' => $destinationNodeId,
        ])->whereHas('definition', fn ($definition) => $definition->whereColumn('published_version_id', 'route_definition_versions.id'))
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $at))
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', $at))
            ->where(fn ($query) => $query->whereNull('offering_version_id')->when($offeringVersionId !== null, fn ($query) => $query->orWhere('offering_version_id', $offeringVersionId)))
            ->orderByDesc('priority')->with('legs')->get();
    }

    public function replaceVersionLegs(string $versionId, array $rows): void
    {
        RouteDefinitionVersionLegRecord::query()->where('route_definition_version_id', $versionId)->delete();
        RouteDefinitionVersionLegRecord::query()->insert($rows);
    }

    public function replaceDefinitionLegs(string $definitionId, array $rows): void
    {
        RouteDefinitionLegRecord::query()->where('route_definition_id', $definitionId)->delete();
        RouteDefinitionLegRecord::query()->insert($rows);
    }

    public function activeDefinitionLegsByOrder(?string $hqId, ?string $definitionId): SupportCollection
    {
        return RouteDefinitionLegRecord::query()
            ->where(['hq_id' => $hqId, 'route_definition_id' => $definitionId, 'status' => RouteDefinitionLegStatus::Active->value])
            ->pluck('route_definition_leg_id', 'leg_order');
    }

    /** @return Builder<RouteDefinitionVersionRecord> */
    private function definitionVersion(?string $hqId, string $definitionId, string $versionId): Builder
    {
        return RouteDefinitionVersionRecord::query()
            ->where(['hq_id' => $hqId, 'route_definition_id' => $definitionId, 'route_definition_version_id' => $versionId])->with('legs');
    }
}
