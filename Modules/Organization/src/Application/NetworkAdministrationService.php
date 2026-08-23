<?php

declare(strict_types=1);

namespace Modules\Organization\Application;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class NetworkAdministrationService
{
    private const NODE_TYPES = ['BRANCH', 'HUB', 'GATEWAY'];
    private const CAPABILITIES = ['PICKUP', 'CONSOLIDATION', 'GATEWAY', 'LINEHAUL', 'DELIVERY', 'CUSTOMER_HANDOFF'];

    public function __construct(
        private AuthorizationContextResolver $authorization,
        private TransactionManager $transactions,
        private AuditWriter $audit,
        private OutboxWriter $outbox,
    ) {}

    /** @param array<string, mixed> $filters */
    public function areas(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        $hqId = $this->access($actor, 'network.area.view');
        $query = DB::table('areas as a')->where('a.hq_id', $hqId)
            ->leftJoin('area_hierarchies as h', fn ($join) => $join->on('h.hq_id', '=', 'a.hq_id')->on('h.child_area_id', '=', 'a.area_id'))
            ->select(['a.*', 'h.parent_area_id']);
        if (($filters['search'] ?? '') !== '') {
            $search = '%'.addcslashes((string) $filters['search'], '%_\\').'%';
            $query->where(fn ($q) => $q->where('a.area_code', 'like', $search)->orWhere('a.area_title', 'like', $search));
        }
        if (($filters['status'] ?? '') !== '') {
            $query->where('a.status', $filters['status']);
        }
        return $query->orderBy('a.area_title')->paginate((int) ($filters['per_page'] ?? 20), ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    /** @return array<string, mixed> */
    public function area(AuthenticatedPrincipal $actor, string $areaId): array
    {
        $hqId = $this->access($actor, 'network.area.view');
        return $this->areaResource($this->areaRow($hqId, $areaId));
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function createArea(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        $hqId = $this->access($actor, 'network.area.manage');
        return $this->transactions->run(function () use ($actor, $hqId, $input, $correlationId): array {
            if (DB::table('areas')->where(['hq_id' => $hqId, 'area_code' => $input['area_code']])->exists()) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'Area code already exists.');
            }
            $areaId = (string) Str::uuid();
            DB::table('areas')->insert([
                'area_id' => $areaId, 'hq_id' => $hqId, 'area_code' => $input['area_code'],
                'area_title' => $input['area_title'], 'status' => 'ACTIVE', 'version' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->replaceParent($hqId, $areaId, $input['parent_area_id'] ?? null);
            $after = $this->area($actor, $areaId);
            $this->record($actor, 'network.area.created', 'AREA', $areaId, $correlationId, null, $after);
            return $after;
        });
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function updateArea(AuthenticatedPrincipal $actor, string $areaId, array $input, string $correlationId): array
    {
        $hqId = $this->access($actor, 'network.area.manage');
        return $this->transactions->run(function () use ($actor, $hqId, $areaId, $input, $correlationId): array {
            $row = DB::table('areas')->where(['hq_id' => $hqId, 'area_id' => $areaId])->lockForUpdate()->first();
            if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            if ((int) $row->version !== (int) $input['expected_version']) {
                throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The Area changed since it was loaded.');
            }
            $before = $this->areaResource($this->areaRow($hqId, $areaId));
            $status = (string) ($input['status'] ?? $row->status);
            if ($status === 'INACTIVE') {
                $activeNodes = DB::table('nodes')->where(['hq_id' => $hqId, 'area_id' => $areaId, 'status' => 'ACTIVE'])->exists();
                $activeChildren = DB::table('area_hierarchies as h')->join('areas as a', 'a.area_id', '=', 'h.child_area_id')
                    ->where(['h.hq_id' => $hqId, 'h.parent_area_id' => $areaId, 'a.status' => 'ACTIVE'])->exists();
                if ($activeNodes || $activeChildren) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Deactivate dependent Nodes and child Areas first.');
            }
            DB::table('areas')->where('area_id', $areaId)->update([
                'area_title' => $input['area_title'] ?? $row->area_title,
                'status' => $status, 'version' => (int) $row->version + 1, 'updated_at' => now(),
            ]);
            if (array_key_exists('parent_area_id', $input)) $this->replaceParent($hqId, $areaId, $input['parent_area_id']);
            $after = $this->area($actor, $areaId);
            $this->record($actor, 'network.area.updated', 'AREA', $areaId, $correlationId, $before, $after);
            return $after;
        });
    }

    /** @param array<string, mixed> $filters */
    public function nodes(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        $hqId = $this->access($actor, 'network.node.view');
        $query = DB::table('nodes')->where('hq_id', $hqId);
        if (($filters['search'] ?? '') !== '') {
            $search = '%'.addcslashes((string) $filters['search'], '%_\\').'%';
            $query->where(fn ($q) => $q->where('node_code', 'like', $search)->orWhere('node_title', 'like', $search));
        }
        foreach (['status', 'area_id', 'node_type'] as $filter) {
            if (($filters[$filter] ?? '') !== '') $query->where($filter, $filters[$filter]);
        }
        return $query->orderBy('node_title')->paginate((int) ($filters['per_page'] ?? 20), ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    /** @return array<string, mixed> */
    public function node(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        $hqId = $this->access($actor, 'network.node.view');
        $row = DB::table('nodes')->where(['hq_id' => $hqId, 'node_id' => $nodeId])->first();
        if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        return $this->nodeResource($row);
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function createNode(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        $hqId = $this->access($actor, 'network.node.manage');
        return $this->transactions->run(function () use ($actor, $hqId, $input, $correlationId): array {
            $this->validateNodeInput($hqId, $input);
            if (DB::table('nodes')->where(['hq_id' => $hqId, 'node_code' => $input['node_code']])->exists()) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'Node code already exists.');
            }
            $nodeId = (string) Str::uuid();
            DB::table('nodes')->insert($this->nodeColumns($input) + [
                'node_id' => $nodeId, 'hq_id' => $hqId, 'node_code' => $input['node_code'],
                'status' => 'ACTIVE', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $after = $this->node($actor, $nodeId);
            $this->record($actor, 'network.node.created', 'NODE', $nodeId, $correlationId, null, $after);
            return $after;
        });
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function updateNode(AuthenticatedPrincipal $actor, string $nodeId, array $input, string $correlationId): array
    {
        $hqId = $this->access($actor, 'network.node.manage');
        return $this->transactions->run(function () use ($actor, $hqId, $nodeId, $input, $correlationId): array {
            $row = DB::table('nodes')->where(['hq_id' => $hqId, 'node_id' => $nodeId])->lockForUpdate()->first();
            if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            if ((int) $row->version !== (int) $input['expected_version']) throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The Node changed since it was loaded.');
            $merged = [
                'area_id' => $input['area_id'] ?? $row->area_id,
                'node_title' => $input['node_title'] ?? $row->node_title,
                'node_type' => $input['node_type'] ?? $row->node_type,
                'capabilities' => $input['capabilities'] ?? json_decode((string) $row->capabilities, true, 512, JSON_THROW_ON_ERROR),
                'address' => $input['address'] ?? $this->addressResource($row),
            ];
            $this->validateNodeInput($hqId, $merged);
            $before = $this->nodeResource($row);
            DB::table('nodes')->where('node_id', $nodeId)->update($this->nodeColumns($merged) + [
                'status' => $input['status'] ?? $row->status,
                'version' => (int) $row->version + 1, 'updated_at' => now(),
            ]);
            $after = $this->node($actor, $nodeId);
            $this->record($actor, 'network.node.updated', 'NODE', $nodeId, $correlationId, $before, $after);
            return $after;
        });
    }

    private function access(AuthenticatedPrincipal $actor, string $permission): string
    {
        if ($actor->hqId === null) throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        $context = $this->authorization->resolve($actor);
        if (! collect($context['module_entitlements'] ?? [])->contains(fn ($e) => ($e['module_code'] ?? null) === 'LiveOperations' && ($e['status'] ?? null) === 'ENABLED')) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        }
        if (! in_array($permission, $context['permissions'] ?? [], true)) throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        return $actor->hqId;
    }

    private function areaRow(string $hqId, string $areaId): object
    {
        $row = DB::table('areas as a')->leftJoin('area_hierarchies as h', fn ($join) => $join->on('h.hq_id', '=', 'a.hq_id')->on('h.child_area_id', '=', 'a.area_id'))
            ->where(['a.hq_id' => $hqId, 'a.area_id' => $areaId])->select(['a.*', 'h.parent_area_id'])->first();
        if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        return $row;
    }

    /** @return array<string, mixed> */
    public function areaResource(object $row): array
    {
        return ['area_id' => (string) $row->area_id, 'area_code' => (string) $row->area_code, 'area_title' => (string) $row->area_title, 'parent_area_id' => $row->parent_area_id === null ? null : (string) $row->parent_area_id, 'status' => (string) $row->status, 'version' => (int) $row->version];
    }

    /** @return array<string, mixed> */
    public function nodeResource(object $row): array
    {
        return ['node_id' => (string) $row->node_id, 'area_id' => (string) $row->area_id, 'node_code' => (string) $row->node_code, 'node_title' => (string) $row->node_title, 'node_type' => (string) $row->node_type, 'capabilities' => json_decode((string) ($row->capabilities ?? '[]'), true, 512, JSON_THROW_ON_ERROR), 'address' => $this->addressResource($row), 'status' => (string) $row->status, 'version' => (int) $row->version];
    }

    /** @return array<string, mixed> */
    private function addressResource(object $row): array
    {
        return ['country_code' => 'IR', 'province_id' => $row->province_id === null ? null : (string) $row->province_id, 'city_id' => $row->city_id === null ? null : (string) $row->city_id, 'postal_code' => $row->postal_code === null ? null : (string) $row->postal_code, 'line' => $row->address_line === null ? null : (string) $row->address_line, 'location' => $row->latitude === null ? null : ['latitude' => (float) $row->latitude, 'longitude' => (float) $row->longitude]];
    }

    /** @param array<string, mixed> $input */
    private function validateNodeInput(string $hqId, array $input): void
    {
        if (! in_array($input['node_type'], self::NODE_TYPES, true) || array_diff($input['capabilities'], self::CAPABILITIES) !== []) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Invalid Node type or capability.');
        if (! DB::table('areas')->where(['hq_id' => $hqId, 'area_id' => $input['area_id'], 'status' => 'ACTIVE'])->exists()) throw new ApiException(ApiErrorCode::ValidationError, 422, 'An active Area in the current HQ is required.');
        $address = $input['address'];
        if (($address['country_code'] ?? null) !== 'IR') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only IR addresses are supported.');
        if (($address['city_id'] ?? null) !== null) {
            $city = DB::table('cities')->where(['city_id' => $address['city_id'], 'is_active' => true])->first();
            if ($city === null || ($address['province_id'] ?? null) !== $city->province_id) throw new ApiException(ApiErrorCode::ValidationError, 422, 'City and Province must be an active canonical pair.');
        } elseif (($address['province_id'] ?? null) !== null && ! DB::table('provinces')->where(['province_id' => $address['province_id'], 'is_active' => true])->exists()) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Province must be active.');
        }
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function nodeColumns(array $input): array
    {
        $address = $input['address']; $location = $address['location'] ?? null;
        return ['area_id' => $input['area_id'], 'node_title' => $input['node_title'], 'node_type' => $input['node_type'], 'capabilities' => json_encode(array_values(array_unique($input['capabilities'])), JSON_THROW_ON_ERROR), 'address_snapshot' => json_encode($address, JSON_THROW_ON_ERROR), 'province_id' => $address['province_id'] ?? null, 'city_id' => $address['city_id'] ?? null, 'country_code' => 'IR', 'postal_code' => $address['postal_code'] ?? null, 'address_line' => $address['line'] ?? null, 'latitude' => $location['latitude'] ?? null, 'longitude' => $location['longitude'] ?? null];
    }

    private function replaceParent(string $hqId, string $areaId, ?string $parentId): void
    {
        DB::table('area_hierarchies')->where(['hq_id' => $hqId, 'child_area_id' => $areaId])->delete();
        if ($parentId === null) return;
        if ($parentId === $areaId || ! DB::table('areas')->where(['hq_id' => $hqId, 'area_id' => $parentId, 'status' => 'ACTIVE'])->exists()) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Parent Area is invalid.');
        $cycle = DB::selectOne('WITH RECURSIVE descendants AS (SELECT child_area_id FROM area_hierarchies WHERE hq_id = ? AND parent_area_id = ? UNION ALL SELECT h.child_area_id FROM area_hierarchies h JOIN descendants d ON h.parent_area_id = d.child_area_id WHERE h.hq_id = ?) SELECT EXISTS(SELECT 1 FROM descendants WHERE child_area_id = ?) AS creates_cycle', [$hqId, $areaId, $hqId, $parentId]);
        if ((bool) ($cycle->creates_cycle ?? false)) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Area hierarchy cycles are not allowed.');
        DB::table('area_hierarchies')->insert(['area_hierarchy_id' => (string) Str::uuid(), 'hq_id' => $hqId, 'parent_area_id' => $parentId, 'child_area_id' => $areaId, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed> $after */
    private function record(AuthenticatedPrincipal $actor, string $action, string $type, string $id, string $correlationId, ?array $before, array $after): void
    {
        $this->audit->write($actor->hqId, $actor->userId, $action, $type, $id, $correlationId, $before, $after);
        $this->outbox->write(
            $actor->hqId,
            $type,
            $id,
            'network.configuration.changed',
            $correlationId,
            ['action' => $action, 'target_type' => $type, 'target_id' => $id],
        );
    }
}
