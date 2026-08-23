<?php

declare(strict_types=1);

namespace Modules\Manifest\Application;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Manifest\Domain\ManifestPolicy;
use Modules\Manifest\Infrastructure\Persistence\ManifestNumberAllocator;
use Modules\Operations\Application\DeliveryTaskService;

final readonly class ManifestService
{
    public function __construct(
        private AuthorizationContextResolver $authorization,
        private TransactionManager $transactions,
        private AuditWriter $audit,
        private OutboxWriter $outbox,
        private ManifestPolicy $policy,
        private ManifestNumberAllocator $numbers,
        private DeliveryTaskService $deliveryTasks,
    ) {}

    /** @param array<string, mixed> $filters */
    public function list(AuthenticatedPrincipal $actor, string $nodeId, array $filters): LengthAwarePaginator
    {
        $this->access($actor, $nodeId, 'manifest.view');
        $query = DB::table('manifests as m')->where(['m.hq_id' => $actor->hqId, 'm.node_id' => $nodeId])
            ->select('m.*');
        if (($filters['search'] ?? null) !== null) {
            $query->where('m.manifest_number', 'like', '%'.addcslashes((string) $filters['search'], '%_\\').'%');
        }
        foreach (['state', 'manifest_status'] as $field) {
            if (($filters[$field] ?? null) !== null) {
                $query->where("m.{$field}", $filters[$field]);
            }
        }
        return $query->orderByDesc('m.created_at')->orderByDesc('m.manifest_id')
            ->paginate((int) ($filters['page_size'] ?? 25), page: (int) ($filters['page'] ?? 1));
    }

    /** @return array<string, mixed> */
    public function create(AuthenticatedPrincipal $actor, string $nodeId, array $input, string $correlationId): array
    {
        $context = $this->access($actor, $nodeId, 'manifest.create');
        $driver = isset($input['assigned_driver_id']) ? (string) $input['assigned_driver_id'] : null;
        $this->policy->assertContext((string) $input['manifest_status'], $driver);
        $this->assertOperationalContext($actor, $nodeId, $input);
        $id = $this->transactions->run(function () use ($actor, $nodeId, $input, $driver, $correlationId): string {
            $id = (string) Str::uuid();
            DB::table('manifests')->insert([
                'manifest_id' => $id, 'hq_id' => $actor->hqId,
                'manifest_number' => $this->numbers->next(), 'node_id' => $nodeId,
                'manifest_status' => $input['manifest_status'], 'assigned_driver_id' => $driver,
                'manifest_type' => $this->manifestType((string) $input['manifest_status']),
                'origin_node_id' => $input['origin_node_id'] ?? ($input['manifest_status'] === 'OF' ? $nodeId : null),
                'destination_node_id' => $input['destination_node_id'] ?? ($input['manifest_status'] === 'IR' ? $nodeId : null),
                'route_plan_id' => $input['route_plan_id'] ?? null,
                'route_plan_leg_id' => $input['route_plan_leg_id'] ?? null,
                'transport_run_id' => $input['transport_run_id'] ?? null,
                'assigned_vehicle_id' => $input['assigned_vehicle_id'] ?? null,
                'state' => 'DRAFT', 'version' => 1, 'created_by' => $actor->userId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit->write($actor->hqId, $actor->userId, 'MANIFEST_CREATED', 'MANIFEST', $id, $correlationId);
            $this->outbox->write($actor->hqId, 'MANIFEST', $id, 'manifest.created', $correlationId, [
                'manifest_id' => $id, 'manifest_status' => $input['manifest_status'], 'version' => '1',
            ]);
            return $id;
        });
        return $this->detail($actor, $nodeId, $id, $context);
    }

    /** @return array<string, mixed> */
    public function get(AuthenticatedPrincipal $actor, string $nodeId, string $id): array
    {
        return $this->detail($actor, $nodeId, $id, $this->access($actor, $nodeId, 'manifest.view'));
    }

    /** @return array<string, mixed> */
    public function update(AuthenticatedPrincipal $actor, string $nodeId, string $id, array $input, string $correlationId): array
    {
        $context = $this->access($actor, $nodeId, 'manifest.edit');
        $this->transactions->run(function () use ($actor, $nodeId, $id, $input, $correlationId): void {
            $manifest = $this->locked($actor, $nodeId, $id);
            $this->version($manifest, (int) $input['expected_version']);
            $this->policy->assertEditable((string) $manifest->state);
            $target = (string) ($input['manifest_status'] ?? $manifest->manifest_status);
            $driver = array_key_exists('assigned_driver_id', $input)
                ? ($input['assigned_driver_id'] === null ? null : (string) $input['assigned_driver_id'])
                : $manifest->assigned_driver_id;
            $this->policy->assertContext($target, $driver);
            $mergedContext = [
                'manifest_status' => $target,
                'origin_node_id' => $input['origin_node_id'] ?? $manifest->origin_node_id,
                'destination_node_id' => $input['destination_node_id'] ?? $manifest->destination_node_id,
                'route_plan_id' => $input['route_plan_id'] ?? $manifest->route_plan_id,
                'route_plan_leg_id' => $input['route_plan_leg_id'] ?? $manifest->route_plan_leg_id,
                'transport_run_id' => $input['transport_run_id'] ?? $manifest->transport_run_id,
                'assigned_driver_id' => $driver,
                'assigned_vehicle_id' => $input['assigned_vehicle_id'] ?? $manifest->assigned_vehicle_id,
            ];
            $this->assertOperationalContext($actor, $nodeId, $mergedContext);
            if ($target !== $manifest->manifest_status
                && DB::table('manifest_parcels')->where('manifest_id', $id)->exists()) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Target status cannot change after Parcel insertion.');
            }
            DB::table('manifests')->where('manifest_id', $id)->update([
                'manifest_status' => $target, 'manifest_type' => $this->manifestType($target),
                'origin_node_id' => $mergedContext['origin_node_id'], 'destination_node_id' => $mergedContext['destination_node_id'],
                'route_plan_id' => $mergedContext['route_plan_id'], 'route_plan_leg_id' => $mergedContext['route_plan_leg_id'],
                'transport_run_id' => $mergedContext['transport_run_id'],
                'assigned_driver_id' => $driver, 'assigned_vehicle_id' => $mergedContext['assigned_vehicle_id'],
                'version' => $manifest->version + 1, 'updated_at' => now(),
            ]);
            $this->audit->write($actor->hqId, $actor->userId, 'MANIFEST_CONTEXT_UPDATED', 'MANIFEST', $id, $correlationId);
        });
        return $this->detail($actor, $nodeId, $id, $context);
    }

    /** @param array<string, mixed> $filters */
    public function eligible(AuthenticatedPrincipal $actor, string $nodeId, string $id, array $filters): LengthAwarePaginator
    {
        $this->access($actor, $nodeId, 'manifest.view');
        $manifest = $this->visible($actor, $nodeId, $id);
        $statuses = $this->policy->sourceStatuses((string) $manifest->manifest_status);
        return DB::table('parcels as p')->join('consignments as c', function ($join): void {
            $join->on('c.consignment_id', '=', 'p.consignment_id')->on('c.hq_id', '=', 'p.hq_id');
        })->where('p.hq_id', $actor->hqId)->whereIn('p.current_status', $statuses)
            ->where(fn ($q) => $q->whereNull('c.service_offering_id')->orWhere('c.commercial_pricing_state', 'LOCKED'))
            ->where(fn ($q) => $this->visibleParcelAtNode($q, $nodeId))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('manifest_parcels as mp')
                ->whereColumn('mp.parcel_id', 'p.parcel_id')->whereNotNull('mp.active_slot'))
            ->when(($filters['search'] ?? null) !== null, fn ($q) => $q->where(fn ($s) => $s
                ->where('p.parcel_number', 'like', '%'.(string) $filters['search'].'%')
                ->orWhere('c.consignment_number', 'like', '%'.(string) $filters['search'].'%')))
            ->select(['p.parcel_id', 'p.parcel_number', 'p.current_status', 'c.consignment_id', 'c.consignment_number', 'c.receiver_contact_name'])
            ->orderBy('p.parcel_number')->paginate((int) ($filters['page_size'] ?? 25), page: (int) ($filters['page'] ?? 1));
    }

    /** @return array{detail: array<string,mixed>, outcomes: list<array<string,mixed>>} */
    public function add(AuthenticatedPrincipal $actor, string $nodeId, string $id, array $input, string $correlationId): array
    {
        $context = $this->access($actor, $nodeId, 'manifest.edit');
        $outcomes = $this->transactions->run(function () use ($actor, $nodeId, $id, $input, $correlationId): array {
            $manifest = $this->locked($actor, $nodeId, $id);
            $this->version($manifest, (int) $input['expected_version']);
            $this->policy->assertEditable((string) $manifest->state);
            $outcomes = [];
            $inserted = 0;
            foreach ($input['identifiers'] as $identifier) {
                $parcels = $this->resolveInput($actor, $nodeId, (string) $identifier);
                if ($parcels === []) {
                    $outcomes[] = ['input' => $identifier, 'result' => 'NOT_FOUND'];
                    continue;
                }
                foreach ($parcels as $parcel) {
                    if (DB::table('manifest_parcels')->where(['manifest_id' => $id, 'parcel_id' => $parcel->parcel_id])->exists()) {
                        $outcomes[] = ['input' => $identifier, 'parcel_number' => $parcel->parcel_number, 'result' => 'DUPLICATE'];
                        continue;
                    }
                    $slot = hash('sha256', "{$actor->hqId}|{$parcel->parcel_id}|{$manifest->manifest_status}");
                    $row = [
                        'manifest_parcel_id' => (string) Str::uuid(), 'hq_id' => $actor->hqId,
                        'manifest_id' => $id, 'parcel_id' => $parcel->parcel_id,
                        'manifest_parcel_status' => 'PENDING', 'failure_code' => null, 'failure_reason' => null,
                        'input_source' => $input['input_source'], 'input_value' => $identifier,
                        'active_slot' => $slot, 'created_by' => $actor->userId,
                        'processed_at' => null, 'created_at' => now(), 'updated_at' => now(),
                    ];
                    try {
                        DB::table('manifest_parcels')->insert($row);
                        $result = 'ADDED';
                    } catch (QueryException $e) {
                        if ($e->getCode() !== '23000') {
                            throw $e;
                        }
                        $row['manifest_parcel_id'] = (string) Str::uuid();
                        $row['manifest_parcel_status'] = 'FAILED';
                        $row['failure_code'] = 'PARCEL_ALREADY_ASSIGNED';
                        $row['failure_reason'] = 'Parcel is active in another Manifest.';
                        $row['active_slot'] = null;
                        $row['processed_at'] = now();
                        DB::table('manifest_parcels')->insert($row);
                        $result = 'FAILED';
                    }
                    $inserted++;
                    $outcomes[] = ['input' => $identifier, 'parcel_number' => $parcel->parcel_number, 'result' => $result];
                }
            }
            if ($inserted > 0) {
                DB::table('manifests')->where('manifest_id', $id)->update(['version' => $manifest->version + 1, 'updated_at' => now()]);
                $this->audit->write($actor->hqId, $actor->userId, 'MANIFEST_PARCELS_ADDED', 'MANIFEST', $id, $correlationId);
            }
            return $outcomes;
        });
        return ['detail' => $this->detail($actor, $nodeId, $id, $context), 'outcomes' => $outcomes];
    }

    /** @return array<string, mixed> */
    public function validate(AuthenticatedPrincipal $actor, string $nodeId, string $id, int $expected, string $correlationId): array
    {
        $context = $this->access($actor, $nodeId, 'manifest.edit');
        $this->transactions->run(function () use ($actor, $nodeId, $id, $expected, $correlationId): void {
            $m = $this->locked($actor, $nodeId, $id); $this->version($m, $expected); $this->policy->assertEditable((string) $m->state);
            foreach (DB::table('manifest_parcels')->where('manifest_id', $id)->whereIn('manifest_parcel_status', ['PENDING', 'VALIDATED'])->lockForUpdate()->get() as $row) {
                $parcel = DB::table('parcels')->where(['hq_id' => $actor->hqId, 'parcel_id' => $row->parcel_id])->first();
                $pricingReady = $parcel !== null && DB::table('consignments')->where(['hq_id' => $actor->hqId, 'consignment_id' => $parcel->consignment_id])->where(fn ($q) => $q->whereNull('service_offering_id')->orWhere('commercial_pricing_state', 'LOCKED'))->exists();
                $valid = $parcel !== null && $pricingReady && $this->policy->canTransition((string) $parcel->current_status, (string) $m->manifest_status) && $this->parcelContextValid($parcel, $m, $nodeId);
                DB::table('manifest_parcels')->where('manifest_parcel_id', $row->manifest_parcel_id)->update($valid ? [
                    'manifest_parcel_status' => 'VALIDATED', 'updated_at' => now(),
                ] : [
                    'manifest_parcel_status' => 'FAILED', 'failure_code' => $pricingReady ? 'INVALID_STATUS_TRANSITION' : 'PRICING_STALE',
                    'failure_reason' => $pricingReady ? 'Parcel is not eligible for the target status.' : 'Consignment pricing must be recalculated before issuance.', 'active_slot' => null,
                    'processed_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('manifests')->where('manifest_id', $id)->update(['state' => 'OPEN', 'version' => $m->version + 1, 'updated_at' => now()]);
            $this->audit->write($actor->hqId, $actor->userId, 'MANIFEST_VALIDATED', 'MANIFEST', $id, $correlationId);
        });
        return $this->detail($actor, $nodeId, $id, $context);
    }

    /** @return array<string, mixed> */
    public function confirm(AuthenticatedPrincipal $actor, string $nodeId, string $id, int $expected, string $correlationId): array
    {
        $context = $this->access($actor, $nodeId, 'manifest.approve');
        $this->transactions->run(function () use ($actor, $nodeId, $id, $expected, $correlationId): void {
            $m = $this->locked($actor, $nodeId, $id); $this->version($m, $expected);
            if ($m->state !== 'OPEN') {
                throw new ApiException(ApiErrorCode::ManifestNotEditable, 422, 'The Manifest must be Open before confirmation.');
            }
            $rows = DB::table('manifest_parcels')
                ->where(['hq_id' => $actor->hqId, 'manifest_id' => $id])
                ->whereIn('manifest_parcel_status', ['PENDING', 'VALIDATED'])
                ->lockForUpdate()
                ->get();
            $success = 0; $consignments = [];
            foreach ($rows as $row) {
                $parcel = DB::table('parcels')->where(['hq_id' => $actor->hqId, 'parcel_id' => $row->parcel_id])->lockForUpdate()->first();
                $pricingReady = $parcel !== null && DB::table('consignments')->where(['hq_id' => $actor->hqId, 'consignment_id' => $parcel->consignment_id])->where(fn ($q) => $q->whereNull('service_offering_id')->orWhere('commercial_pricing_state', 'LOCKED'))->exists();
                if ($parcel === null || ! $pricingReady || ! $this->policy->canTransition((string) $parcel->current_status, (string) $m->manifest_status) || ! $this->parcelContextValid($parcel, $m, $nodeId)) {
                    DB::table('manifest_parcels')->where('manifest_parcel_id', $row->manifest_parcel_id)->update([
                        'manifest_parcel_status' => 'FAILED', 'failure_code' => $pricingReady ? 'INVALID_STATUS_TRANSITION' : 'PRICING_STALE',
                        'failure_reason' => $pricingReady ? 'Parcel eligibility changed before confirmation.' : 'Consignment pricing must be recalculated before issuance.', 'active_slot' => null,
                        'processed_at' => now(), 'updated_at' => now(),
                    ]);
                    continue;
                }
                $custody = $m->manifest_status === 'OD' ? 'DELIVERY_DRIVER' : 'NODE';
                $custodian = $m->manifest_status === 'OD' ? $m->assigned_driver_id : $nodeId;
                $nextNode = $m->manifest_status === 'OD' ? null : $nodeId;
                DB::table('parcels')->where('parcel_id', $parcel->parcel_id)->update([
                    'current_status' => $m->manifest_status, 'current_node_id' => $nextNode,
                    'current_custody_type' => $custody, 'current_custodian_id' => $custodian,
                    'active_transport_run_id' => $m->manifest_status === 'IR' ? null : $parcel->active_transport_run_id,
                    'version' => (int) $parcel->version + 1, 'updated_at' => now(),
                ]);
                $this->statusEvent($actor, $nodeId, (string) $parcel->consignment_id, (string) $parcel->parcel_id, (string) $parcel->current_status, (string) $m->manifest_status, $id);
                DB::table('parcel_custody_events')->insert([
                    'custody_event_id' => (string) Str::uuid(), 'hq_id' => $actor->hqId,
                    'event_sequence' => $this->nextSequence('parcel_custody_events', (string) $parcel->consignment_id),
                    'consignment_id' => $parcel->consignment_id, 'parcel_id' => $parcel->parcel_id,
                    'from_node_id' => $parcel->current_node_id, 'to_node_id' => $nextNode,
                    'from_custody_type' => $parcel->current_custody_type, 'to_custody_type' => $custody,
                    'from_custodian_id' => $parcel->current_custodian_id, 'to_custodian_id' => $custodian,
                    'command_name' => 'MANIFEST_CONFIRMED', 'initiator_id' => $actor->userId,
                    'manifest_id' => $id, 'route_plan_id' => $m->route_plan_id,
                    'route_plan_leg_id' => $m->route_plan_leg_id, 'transport_run_id' => $m->transport_run_id,
                    'created_at' => now(),
                ]);
                DB::table('manifest_parcels')->where('manifest_parcel_id', $row->manifest_parcel_id)->update([
                    'manifest_parcel_status' => 'SUCCEEDED', 'active_slot' => null, 'processed_at' => now(), 'updated_at' => now(),
                ]);
                $consignments[] = (string) $parcel->consignment_id; $success++;
            }
            $hasUnresolvedActiveRow = DB::table('manifest_parcels')
                ->where(['hq_id' => $actor->hqId, 'manifest_id' => $id])
                ->where(function ($query): void {
                    $query->whereIn('manifest_parcel_status', ['PENDING', 'VALIDATED'])
                        ->orWhereNotNull('active_slot');
                })
                ->lockForUpdate()
                ->exists();
            if ($hasUnresolvedActiveRow) {
                throw new ApiException(
                    ApiErrorCode::ManifestNotEditable,
                    422,
                    'The Manifest still contains unresolved active Parcels.',
                );
            }
            if ($success === 0) {
                throw new ApiException(ApiErrorCode::ManifestNoSuccessfulParcels, 422, 'No Parcel can be confirmed.');
            }
            foreach (array_unique($consignments) as $consignmentId) {
                $statuses = DB::table('parcels')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignmentId])->distinct()->pluck('current_status');
                if ($statuses->count() === 1) {
                    $old = DB::table('consignments')->where('consignment_id', $consignmentId)->value('current_status');
                    if ($old !== $m->manifest_status) {
                        DB::table('consignments')->where('consignment_id', $consignmentId)->update(['current_status' => $m->manifest_status, 'updated_at' => now()]);
                        $this->statusEvent($actor, $nodeId, $consignmentId, null, (string) $old, (string) $m->manifest_status, $id);
                    }
                }
                if ($m->manifest_status === 'OD') {
                    $this->deliveryTasks->activateFromManifest($actor, $nodeId, $consignmentId, (string) $m->assigned_driver_id, $id);
                }
                if ($m->manifest_status === 'IR' && $m->transport_run_id !== null
                    && DB::table('consignments')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignmentId, 'delivery_node_id' => $nodeId])->exists()) {
                    $this->deliveryTasks->ensurePending($actor, $nodeId, $consignmentId);
                }
            }
            $this->applyRouteProgress($m);
            DB::table('manifests')->where('manifest_id', $id)->update([
                'state' => 'CLOSED', 'approved_by' => $actor->userId, 'closed_at' => now(),
                'version' => $m->version + 1, 'updated_at' => now(),
            ]);
            $this->audit->write($actor->hqId, $actor->userId, 'MANIFEST_CONFIRMED', 'MANIFEST', $id, $correlationId);
            $this->outbox->write($actor->hqId, 'MANIFEST', $id, 'manifest.closed', $correlationId, [
                'manifest_id' => $id, 'manifest_status' => $m->manifest_status, 'succeeded_count' => (string) $success,
            ]);
        });
        return $this->detail($actor, $nodeId, $id, $context);
    }

    /** @return array<string, mixed> */
    public function listItem(object|array $row): array
    {
        $r = (array) $row; $counts = $this->counts((string) $r['manifest_id']);
        return [
            'manifest_id' => (string) $r['manifest_id'], 'manifest_number' => (string) $r['manifest_number'],
            'node_id' => (string) $r['node_id'], 'manifest_status' => (string) $r['manifest_status'],
            'state' => (string) $r['state'], 'manifest_type' => $r['manifest_type'] === null ? $this->manifestType((string) $r['manifest_status']) : (string) $r['manifest_type'],
            'origin_node_id' => $r['origin_node_id'], 'destination_node_id' => $r['destination_node_id'],
            'route_plan_id' => $r['route_plan_id'], 'route_plan_leg_id' => $r['route_plan_leg_id'],
            'transport_run_id' => $r['transport_run_id'],
            'assigned_driver_id' => $r['assigned_driver_id'], 'assigned_vehicle_id' => $r['assigned_vehicle_id'],
            'version' => (int) $r['version'], 'total_count' => array_sum($counts),
            'succeeded_count' => $counts['succeeded'], 'failed_count' => $counts['failed'],
            'created_at' => $this->time($r['created_at']), 'updated_at' => $this->time($r['updated_at']),
        ];
    }

    /** @return array<string, mixed> */
    private function detail(AuthenticatedPrincipal $actor, string $nodeId, string $id, array $context): array
    {
        $m = $this->visible($actor, $nodeId, $id); $base = $this->listItem($m);
        $parcels = DB::table('manifest_parcels as mp')->join('parcels as p', function ($j): void {
            $j->on('p.parcel_id', '=', 'mp.parcel_id')->on('p.hq_id', '=', 'mp.hq_id');
        })->join('consignments as c', function ($j): void {
            $j->on('c.consignment_id', '=', 'p.consignment_id')->on('c.hq_id', '=', 'p.hq_id');
        })->where('mp.manifest_id', $id)->orderBy('mp.created_at')->get([
            'mp.*', 'p.parcel_number', 'p.current_status', 'c.consignment_id', 'c.consignment_number', 'c.receiver_contact_name',
        ])->map(fn ($r): array => [
            'manifest_parcel_id' => (string) $r->manifest_parcel_id, 'parcel_id' => (string) $r->parcel_id,
            'parcel_number' => (string) $r->parcel_number, 'consignment_id' => (string) $r->consignment_id,
            'consignment_number' => (string) $r->consignment_number, 'receiver_contact_name' => (string) $r->receiver_contact_name,
            'current_status' => (string) $r->current_status, 'manifest_parcel_status' => (string) $r->manifest_parcel_status,
            'failure_code' => $r->failure_code, 'failure_reason' => $r->failure_reason,
            'input_source' => (string) $r->input_source, 'input_value' => (string) $r->input_value,
            'processed_at' => $r->processed_at ? $this->time($r->processed_at) : null, 'created_at' => $this->time($r->created_at),
        ])->all();
        $actions = [];
        if (in_array($m->state, ['DRAFT', 'OPEN'], true) && in_array('manifest.edit', $context['permissions'], true)) {
            $actions = ['EDIT', 'INSERT', 'VALIDATE'];
        }
        if ($m->state === 'OPEN' && in_array('manifest.approve', $context['permissions'], true)) {
            $actions[] = 'CONFIRM';
        }
        return [...$base, 'created_by' => (string) $m->created_by, 'approved_by' => $m->approved_by,
            'closed_at' => $m->closed_at ? $this->time($m->closed_at) : null, 'parcels' => $parcels,
            'bucket_counts' => $this->counts($id), 'permitted_actions' => $actions];
    }

    private function access(AuthenticatedPrincipal $actor, string $nodeId, string $permission): array
    {
        if ($actor->hqId === null) throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        $context = $this->authorization->resolve($actor);
        if (! collect($context['module_entitlements'])->contains(fn ($e) => $e['module_code'] === 'Manifest' && $e['status'] === 'ENABLED')) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        }
        if (! in_array($permission, $context['permissions'], true)) throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        if (! in_array($nodeId, $context['accessible_node_ids'], true)) throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
        return $context;
    }

    private function visible(AuthenticatedPrincipal $actor, string $nodeId, string $id): object
    {
        $row = DB::table('manifests')->where(['hq_id' => $actor->hqId, 'node_id' => $nodeId, 'manifest_id' => $id])->first();
        if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        return $row;
    }

    private function locked(AuthenticatedPrincipal $actor, string $nodeId, string $id): object
    {
        $row = DB::table('manifests')->where(['hq_id' => $actor->hqId, 'node_id' => $nodeId, 'manifest_id' => $id])->lockForUpdate()->first();
        if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        return $row;
    }

    private function version(object $m, int $expected): void
    {
        if ((int) $m->version !== $expected) throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The Manifest version is stale.', details: ['current_version' => (int) $m->version]);
    }

    /** @return list<object> */
    private function resolveInput(AuthenticatedPrincipal $actor, string $nodeId, string $identifier): array
    {
        return DB::table('parcels as p')->join('consignments as c', function ($j): void {
            $j->on('c.consignment_id', '=', 'p.consignment_id')->on('c.hq_id', '=', 'p.hq_id');
        })->where('p.hq_id', $actor->hqId)->where(fn ($q) => $this->visibleParcelAtNode($q, $nodeId))
            ->where(fn ($q) => $q->where('p.parcel_number', $identifier)->orWhere('c.consignment_number', $identifier))
            ->select(['p.parcel_id', 'p.parcel_number'])->orderBy('p.parcel_number')->get()->all();
    }

    /** @return array{pending:int,validated:int,succeeded:int,failed:int,skipped:int} */
    private function counts(string $id): array
    {
        $raw = DB::table('manifest_parcels')->where('manifest_id', $id)->selectRaw('manifest_parcel_status, COUNT(*) total')->groupBy('manifest_parcel_status')->pluck('total', 'manifest_parcel_status');
        return ['pending' => (int) ($raw['PENDING'] ?? 0), 'validated' => (int) ($raw['VALIDATED'] ?? 0),
            'succeeded' => (int) ($raw['SUCCEEDED'] ?? 0), 'failed' => (int) ($raw['FAILED'] ?? 0), 'skipped' => (int) ($raw['SKIPPED'] ?? 0)];
    }

    private function statusEvent(AuthenticatedPrincipal $actor, string $nodeId, string $consignmentId, ?string $parcelId, string $old, string $new, string $manifestId): void
    {
        DB::table('consignment_status_events')->insert([
            'status_event_id' => (string) Str::uuid(), 'hq_id' => $actor->hqId,
            'event_sequence' => $this->nextSequence('consignment_status_events', $consignmentId),
            'consignment_id' => $consignmentId, 'parcel_id' => $parcelId,
            'previous_status' => $old, 'new_status' => $new, 'initiator_id' => $actor->userId,
            'node_id' => $nodeId, 'manifest_id' => $manifestId,
            'reason_code' => 'MANIFEST_CONFIRMED', 'created_at' => now(),
        ]);
    }

    private function nextSequence(string $table, string $consignmentId): int
    {
        return ((int) DB::table($table)->where('consignment_id', $consignmentId)->max('event_sequence')) + 1;
    }

    private function time(mixed $value): string
    {
        return \Carbon\CarbonImmutable::parse((string) $value, 'UTC')->utc()->toISOString();
    }

    /** @param array<string,mixed> $input */
    private function assertOperationalContext(AuthenticatedPrincipal $actor, string $nodeId, array $input): void
    {
        $target = (string) $input['manifest_status'];
        if ($target === 'OD') {
            $driverId = (string) ($input['assigned_driver_id'] ?? '');
            $eligible = DB::table('drivers as d')->where(['d.hq_id' => $actor->hqId, 'd.driver_id' => $driverId, 'd.home_node_id' => $nodeId, 'd.status' => 'ACTIVE', 'd.availability_status' => 'AVAILABLE'])
                ->whereExists(fn ($q) => $q->selectRaw('1')->from('driver_capabilities as dc')->whereColumn('dc.driver_id', 'd.driver_id')->where('dc.capability', 'DELIVERY'))->exists();
            if (! $eligible) throw new ApiException(ApiErrorCode::ValidationError, 422, 'An active available delivery driver in scope is required.');
        }
        if (in_array($target, ['OF', 'IR'], true) && isset($input['route_plan_leg_id'])) {
            $leg = DB::table('route_plan_legs')->where(['hq_id' => $actor->hqId, 'route_plan_leg_id' => $input['route_plan_leg_id']])->first();
            if ($leg === null) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The route leg does not belong to this tenant.');
            $expectedNode = $target === 'OF' ? $leg->origin_node_id : $leg->destination_node_id;
            if ((string) $expectedNode !== $nodeId) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The route leg does not belong to the executing node.');
        }
    }

    private function manifestType(string $target): string
    {
        return match ($target) { 'IR' => 'INBOUND_RECEPTION', 'OF' => 'OUTBOUND_TRANSFER', 'OD' => 'DELIVERY_ASSIGNMENT' };
    }

    private function parcelContextValid(object $parcel, object $manifest, string $nodeId): bool
    {
        if ($manifest->manifest_status === 'IR' && $parcel->current_status === 'PU') {
            if ((string) $parcel->current_custody_type === 'PICKUP_DRIVER') return true;
            return DB::table('consignments')->where(['hq_id' => $manifest->hq_id, 'consignment_id' => $parcel->consignment_id, 'pickup_node_id' => $nodeId])->exists();
        }
        if ($manifest->manifest_status === 'IR' && $parcel->current_status === 'OS') {
            if ($manifest->transport_run_id === null || (string) $parcel->active_transport_run_id !== (string) $manifest->transport_run_id) return false;
            return DB::table('transport_runs')->where(['hq_id' => $manifest->hq_id, 'transport_run_id' => $manifest->transport_run_id, 'destination_node_id' => $nodeId, 'status' => 'ARRIVED'])->exists();
        }
        if ($manifest->manifest_status === 'OF') {
            if ($parcel->active_route_plan_id === null && $manifest->route_plan_id === null) {
                return DB::table('consignments')->where(['hq_id' => $manifest->hq_id, 'consignment_id' => $parcel->consignment_id])->where(fn ($q) => $q->where('pickup_node_id', $nodeId)->orWhere('delivery_node_id', $nodeId))->exists();
            }
            return (string) $parcel->current_node_id === $nodeId && (string) $parcel->active_route_plan_leg_id === (string) $manifest->route_plan_leg_id;
        }
        if ($manifest->manifest_status === 'OD') {
            $allReceived = DB::table('route_plan_legs')->where('route_plan_id', $parcel->active_route_plan_id)->whereNot('status', 'RECEIVED')->doesntExist();
            return (string) $parcel->current_node_id === $nodeId && $allReceived;
        }
        return false;
    }

    private function applyRouteProgress(object $manifest): void
    {
        if ($manifest->route_plan_leg_id === null) return;
        if ($manifest->manifest_status === 'OF') {
            DB::table('route_plan_legs')->where(['route_plan_leg_id' => $manifest->route_plan_leg_id, 'status' => 'ROUTED'])->update(['status' => 'OUTBOUND_CONFIRMED', 'updated_at' => now()]);
        }
        if ($manifest->manifest_status === 'IR' && $manifest->transport_run_id !== null) {
            DB::table('route_plan_legs')->where('route_plan_leg_id', $manifest->route_plan_leg_id)->update(['status' => 'RECEIVED', 'received_at' => now(), 'updated_at' => now()]);
            $remaining = DB::table('route_plan_legs')->where('route_plan_id', $manifest->route_plan_id)->whereNot('status', 'RECEIVED')->exists();
            if (! $remaining) DB::table('route_plans')->where('route_plan_id', $manifest->route_plan_id)->update(['status' => 'COMPLETED', 'active_slot' => null, 'updated_at' => now()]);
        }
    }

    private function visibleParcelAtNode($query, string $nodeId): void
    {
        $query->where('p.current_node_id', $nodeId)
            ->orWhereExists(fn ($q) => $q->selectRaw('1')->from('route_plan_legs as rpl')->whereColumn('rpl.route_plan_leg_id', 'p.active_route_plan_leg_id')->where('rpl.destination_node_id', $nodeId)->whereIn('rpl.status', ['IN_TRANSIT', 'ARRIVED']))
            ->orWhereExists(fn ($q) => $q->selectRaw('1')->from('pickup_tasks as pt')->whereColumn('pt.consignment_id', 'p.consignment_id')->where('pt.node_id', $nodeId)->whereIn('pt.status', ['PENDING', 'ASSIGNED', 'IN_PROGRESS']))
            ->orWhereExists(fn ($q) => $q->selectRaw('1')->from('delivery_tasks as dt')->whereColumn('dt.consignment_id', 'p.consignment_id')->where('dt.node_id', $nodeId)->whereIn('dt.status', ['PENDING', 'ASSIGNED', 'IN_PROGRESS']))
            ->orWhere('c.pickup_node_id', $nodeId)->orWhere('c.delivery_node_id', $nodeId);
    }
}
