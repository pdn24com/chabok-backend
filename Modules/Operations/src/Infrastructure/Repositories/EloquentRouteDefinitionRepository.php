<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Application\Data\Page;
use Modules\Operations\Application\Repositories\RouteDefinitionRepository;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionLegRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionLegRecord;

final class EloquentRouteDefinitionRepository implements RouteDefinitionRepository
{
    public function definitions(string $hq, array $filters): Page
    {
        $query = RouteDefinitionRecord::query()->toBase()->where('hq_id', $hq);
        if (($filters['search'] ?? null) !== null) {
            $query->where(fn($q) => $q->where('route_code', 'like', '%' . $filters['search'] . '%')->orWhere('route_title', 'like', '%' . $filters['search'] . '%'));
        }
        if (($filters['purpose'] ?? null) !== null) {
            $query->whereExists(fn($q) => $q->selectRaw('1')->from('route_definition_versions as v')->whereColumn('v.route_definition_id', 'route_definitions.route_definition_id')->where('v.purpose', $filters['purpose']));
        }
        $page = $query->orderBy('route_code')->paginate((int) ($filters['per_page'] ?? 20), ['*'], 'page', (int) ($filters['page'] ?? 1));
        return new Page($page->items(), $page->currentPage(), $page->perPage(), $page->total());
    }

    public function codeExists(string $hq, string $code): bool
    {
        return RouteDefinitionRecord::query()->toBase()->where(['hq_id' => $hq, 'route_code' => $code])->exists();
    }

    public function definition(string $hq, string $id): ?object
    {
        return RouteDefinitionRecord::query()->toBase()->where(['hq_id' => $hq, 'route_definition_id' => $id])->first();
    }

    public function definitionExists(string $hq, string $definitionId): bool
    {
        return RouteDefinitionRecord::query()->toBase()->where(['hq_id' => $hq, 'route_definition_id' => $definitionId])->exists();
    }

    public function history(string $hq, string $definitionId, int $page, int $perPage): Page
    {
        $result = RouteDefinitionVersionRecord::query()->toBase()->where(['hq_id' => $hq, 'route_definition_id' => $definitionId])->orderByDesc('version_number')->paginate($perPage, ['*'], 'page', $page);
        return new Page($result->items(), $result->currentPage(), $result->perPage(), $result->total());
    }

    public function lastVersionNumber(string $definitionId): int
    {
        return (int) RouteDefinitionVersionRecord::query()->toBase()->where('route_definition_id', $definitionId)->max('version_number');
    }

    public function insertDefinition(array $attributes): void
    {
        RouteDefinitionRecord::query()->toBase()->insert($attributes);
    }

    public function insertVersion(array $attributes): void
    {
        RouteDefinitionVersionRecord::query()->toBase()->insert($attributes);
    }

    public function insertVersionLeg(array $attributes): void
    {
        RouteDefinitionVersionLegRecord::query()->toBase()->insert($attributes);
    }

    public function insertLegacyLeg(array $attributes): void
    {
        RouteDefinitionLegRecord::query()->toBase()->insert($attributes);
    }

    public function deleteVersionLegs(string $id): void
    {
        RouteDefinitionVersionLegRecord::query()->toBase()->where('route_definition_version_id', $id)->delete();
    }

    public function deleteLegacyLegs(string $id): void
    {
        RouteDefinitionLegRecord::query()->toBase()->where('route_definition_id', $id)->delete();
    }

    public function updateVersion(string $versionId, array $changes): void
    {
        RouteDefinitionVersionRecord::query()->toBase()->where('route_definition_version_id', $versionId)->update($changes);
    }

    public function publishDefinition(string $definitionId, string $versionId, \DateTimeInterface $at): void
    {
        RouteDefinitionRecord::query()->toBase()->where('route_definition_id', $definitionId)->update([
            'published_version_id' => $versionId,
            'status' => 'ACTIVE',
            'version' => DB::raw('version + 1'),
            'updated_at' => $at,
        ]);
    }

    public function supersedeDefinition(string $definitionId, string $versionId, \DateTimeInterface $at): void
    {
        RouteDefinitionRecord::query()->toBase()->where(['route_definition_id' => $definitionId, 'published_version_id' => $versionId])->update([
            'published_version_id' => null,
            'status' => 'INACTIVE',
            'version' => DB::raw('version + 1'),
            'updated_at' => $at,
        ]);
    }

    public function matchingVersions(
        string $hqId,
        string $purpose,
        string $originNodeId,
        string $destinationNodeId,
        ?string $offeringVersionId,
        \DateTimeInterface $at,
    ): array
    {
        return RouteDefinitionVersionRecord::query()->toBase()->from('route_definition_versions as v')->join('route_definitions as d', 'd.route_definition_id', '=', 'v.route_definition_id')->where([
            'v.hq_id' => $hqId,
            'v.status' => 'PUBLISHED',
            'v.purpose' => $purpose,
            'v.origin_node_id' => $originNodeId,
            'v.destination_node_id' => $destinationNodeId,
        ])->whereColumn('d.published_version_id', 'v.route_definition_version_id')->where(fn($q) => $q->whereNull('v.effective_from')->orWhere('v.effective_from', '<=', $at))->where(fn($q) => $q->whereNull('v.effective_to')->orWhere('v.effective_to', '>', $at))->where(fn($q) => $q->whereNull('v.offering_version_id')->when($offeringVersionId !== null, fn($inner) => $inner->orWhere('v.offering_version_id', $offeringVersionId)))->orderByDesc('v.priority')->get(['v.*'])->all();
    }

    public function offeringVisible(string $hq, string $offeringVersionId): bool
    {
        return DB::table('service_offering_versions as v')->join('service_offerings as i', 'i.service_offering_id', '=', 'v.service_offering_id')->where('v.service_offering_version_id', $offeringVersionId)->where(fn($q) => $q->whereNull('i.hq_id')->orWhere('i.hq_id', $hq))->exists();
    }

    public function activeNode(string $hq, string $nodeId): bool
    {
        return DB::table('nodes')->where(['hq_id' => $hq, 'node_id' => $nodeId, 'status' => 'ACTIVE'])->exists();
    }

    public function versionExists(string $hq, string $versionId): bool
    {
        return RouteDefinitionVersionRecord::query()->toBase()->where(['hq_id' => $hq, 'route_definition_version_id' => $versionId])->exists();
    }

    public function version(string $hq, string $definition, string $version): ?object
    {
        return RouteDefinitionVersionRecord::query()->toBase()->where(['hq_id' => $hq, 'route_definition_id' => $definition, 'route_definition_version_id' => $version])->first();
    }

    public function lockVersion(string $hq, string $definition, string $version): ?object
    {
        return RouteDefinitionVersionRecord::query()->toBase()->where(['hq_id' => $hq, 'route_definition_id' => $definition, 'route_definition_version_id' => $version])->lockForUpdate()->first();
    }

    public function versionLegs(string $versionId): array
    {
        return RouteDefinitionVersionLegRecord::query()->toBase()->where('route_definition_version_id', $versionId)->orderBy('leg_order')->get()->all();
    }
}
