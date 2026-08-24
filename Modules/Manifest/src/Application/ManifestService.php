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
use Modules\Manifest\Domain\ManifestEligibilityReason;
use Modules\Manifest\Domain\ManifestPolicy;
use Modules\Manifest\Infrastructure\Persistence\ManifestNumberAllocator;

final readonly class ManifestService
{
    public function __construct(
        private AuthorizationContextResolver $authorization,
        private TransactionManager $transactions,
        private AuditWriter $audit,
        private OutboxWriter $outbox,
        private ManifestPolicy $policy,
        private ManifestNumberAllocator $numbers,
        private ManifestOperationalContext $operationalContext,
        private ManifestEligibilityEvaluator $eligibility,
        private ManifestOrchestrationService $orchestration,
    ) {}

    /** @return array<string, mixed> */
    public function contextOptions(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        $this->access($actor, $nodeId, 'manifest.view');

        return $this->operationalContext->options($actor, $nodeId);
    }

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
        $normalized = $this->operationalContext->normalize($actor, $nodeId, $input);
        $this->assertDriverVisibility($context, (string) $normalized['manifest_status']);
        $this->policy->assertContext((string) $normalized['manifest_status'], $normalized['assigned_driver_id']);
        $id = $this->transactions->run(function () use ($actor, $nodeId, $normalized, $correlationId): string {
            $id = (string) Str::uuid();
            DB::table('manifests')->insert([
                'manifest_id' => $id, 'hq_id' => $actor->hqId,
                'manifest_number' => $this->numbers->next(), 'node_id' => $nodeId,
                'manifest_status' => $normalized['manifest_status'], 'context_key' => $normalized['context_key'],
                'manifest_type' => $normalized['manifest_type'],
                'operational_context_type' => $normalized['operational_context_type'],
                'origin_node_id' => $normalized['origin_node_id'],
                'destination_node_id' => $normalized['destination_node_id'],
                'route_plan_id' => $normalized['route_plan_id'],
                'route_definition_version_id' => $normalized['route_definition_version_id'],
                'route_plan_leg_id' => $normalized['route_plan_leg_id'],
                'route_definition_version_leg_id' => $normalized['route_definition_version_leg_id'],
                'source_manifest_id' => $normalized['source_manifest_id'],
                'assigned_driver_id' => $normalized['assigned_driver_id'],
                'assigned_vehicle_id' => $normalized['assigned_vehicle_id'],
                'state' => 'DRAFT', 'version' => 1, 'created_by' => $actor->userId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit->write($actor->hqId, $actor->userId, 'MANIFEST_CREATED', 'MANIFEST', $id, $correlationId);
            $this->outbox->write($actor->hqId, 'MANIFEST', $id, 'manifest.created', $correlationId, [
                'manifest_id' => $id, 'manifest_status' => $normalized['manifest_status'], 'version' => '1',
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
            $mergedContext = [
                'manifest_status' => (string) ($input['manifest_status'] ?? $manifest->manifest_status),
                'context_key' => (string) ($input['context_key'] ?? $manifest->context_key),
                'assigned_driver_id' => array_key_exists('assigned_driver_id', $input) ? $input['assigned_driver_id'] : $manifest->assigned_driver_id,
                'assigned_vehicle_id' => array_key_exists('assigned_vehicle_id', $input) ? $input['assigned_vehicle_id'] : $manifest->assigned_vehicle_id,
            ];
            $normalized = $this->operationalContext->normalize($actor, $nodeId, $mergedContext);
            $resolvedContext = $this->authorization->resolve($actor);
            $this->assertDriverVisibility($resolvedContext, (string) $normalized['manifest_status']);
            $this->policy->assertContext((string) $normalized['manifest_status'], $normalized['assigned_driver_id']);
            if ($normalized['manifest_status'] !== $manifest->manifest_status
                && DB::table('manifest_parcels')->where('manifest_id', $id)->exists()) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Target status cannot change after Parcel insertion.');
            }
            DB::table('manifests')->where('manifest_id', $id)->update([
                'manifest_status' => $normalized['manifest_status'],
                'manifest_type' => $normalized['manifest_type'],
                'operational_context_type' => $normalized['operational_context_type'],
                'context_key' => $normalized['context_key'],
                'origin_node_id' => $normalized['origin_node_id'], 'destination_node_id' => $normalized['destination_node_id'],
                'route_plan_id' => $normalized['route_plan_id'], 'route_definition_version_id' => $normalized['route_definition_version_id'],
                'route_plan_leg_id' => $normalized['route_plan_leg_id'], 'route_definition_version_leg_id' => $normalized['route_definition_version_leg_id'],
                'source_manifest_id' => $normalized['source_manifest_id'],
                'assigned_driver_id' => $normalized['assigned_driver_id'], 'assigned_vehicle_id' => $normalized['assigned_vehicle_id'],
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
        $query = DB::table('parcels as p')->join('consignments as c', function ($join): void {
            $join->on('c.consignment_id', '=', 'p.consignment_id')->on('c.hq_id', '=', 'p.hq_id');
        })->where('p.hq_id', $actor->hqId)
            ->where(fn ($q) => $q->whereNull('c.service_offering_id')->orWhere('c.commercial_pricing_state', 'LOCKED'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('manifest_parcels as mp')
                ->whereColumn('mp.parcel_id', 'p.parcel_id')->whereNotNull('mp.active_slot'))
            ->when(($filters['search'] ?? null) !== null, fn ($q) => $q->where(fn ($s) => $s
                ->where('p.parcel_number', 'like', '%'.(string) $filters['search'].'%')
                ->orWhere('c.consignment_number', 'like', '%'.(string) $filters['search'].'%')))
            ->select(['p.*', 'c.consignment_number', 'c.receiver_contact_name']);
        $this->eligibility->applyCandidateScope($query, $manifest, $nodeId);
        $paginator = $query->orderBy('p.parcel_number')
            ->paginate((int) ($filters['page_size'] ?? 25), page: (int) ($filters['page'] ?? 1));
        $paginator->setCollection($paginator->getCollection()->map(fn (object $row): array => [
            'parcel_id' => (string) $row->parcel_id,
            'parcel_number' => (string) $row->parcel_number,
            'consignment_id' => (string) $row->consignment_id,
            'consignment_number' => (string) $row->consignment_number,
            'receiver_contact_name' => (string) $row->receiver_contact_name,
            'current_status' => (string) $row->current_status,
            'eligibility' => $this->eligibility->evaluate($row, $manifest, $nodeId),
        ]));

        return $paginator;
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
                    $reason = ManifestEligibilityReason::metadata(ManifestEligibilityReason::ParcelNotFound);
                    $outcomes[] = ['input' => $identifier, 'result' => 'NOT_FOUND', ...$reason];
                    continue;
                }
                foreach ($parcels as $parcel) {
                    if (DB::table('manifest_parcels')->where(['manifest_id' => $id, 'parcel_id' => $parcel->parcel_id])->exists()) {
                        $outcomes[] = [
                            'input' => $identifier, 'parcel_number' => $parcel->parcel_number,
                            'result' => 'DUPLICATE',
                            ...$this->eligibility->evaluate($parcel, $manifest, $nodeId),
                        ];
                        continue;
                    }
                    $eligibility = $this->eligibility->evaluate($parcel, $manifest, $nodeId);
                    $slot = hash('sha256', "{$actor->hqId}|{$parcel->parcel_id}|{$manifest->manifest_status}");
                    $row = [
                        'manifest_parcel_id' => (string) Str::uuid(), 'hq_id' => $actor->hqId,
                        'manifest_id' => $id, 'parcel_id' => $parcel->parcel_id,
                        'manifest_parcel_status' => $eligibility['eligible'] ? 'PENDING' : 'FAILED',
                        'failure_code' => $eligibility['eligible'] ? null : $eligibility['reason_code'],
                        'failure_reason' => $eligibility['eligible'] ? null : $eligibility['presentation']['detail']['en'],
                        'input_source' => $input['input_source'], 'input_value' => $identifier,
                        'active_slot' => $eligibility['eligible'] ? $slot : null, 'created_by' => $actor->userId,
                        'processed_at' => $eligibility['eligible'] ? null : now(), 'created_at' => now(), 'updated_at' => now(),
                    ];
                    try {
                        DB::table('manifest_parcels')->insert($row);
                        $result = $eligibility['eligible'] ? 'ADDED' : 'FAILED';
                    } catch (QueryException $e) {
                        if ($e->getCode() !== '23000') {
                            throw $e;
                        }
                        $eligibility = ManifestEligibilityReason::metadata(ManifestEligibilityReason::ParcelAlreadyAssigned);
                        $row['manifest_parcel_id'] = (string) Str::uuid();
                        $row['manifest_parcel_status'] = 'FAILED';
                        $row['failure_code'] = $eligibility['reason_code'];
                        $row['failure_reason'] = $eligibility['presentation']['detail']['en'];
                        $row['active_slot'] = null;
                        $row['processed_at'] = now();
                        DB::table('manifest_parcels')->insert($row);
                        $result = 'FAILED';
                    }
                    $inserted++;
                    $outcomes[] = [
                        'input' => $identifier, 'parcel_number' => $parcel->parcel_number,
                        'result' => $result, ...$eligibility,
                    ];
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
                $eligibility = $parcel === null
                    ? ManifestEligibilityReason::metadata(ManifestEligibilityReason::ParcelNotFound)
                    : $this->eligibility->evaluate($parcel, $m, $nodeId);
                DB::table('manifest_parcels')->where('manifest_parcel_id', $row->manifest_parcel_id)->update($eligibility['eligible'] ? [
                    'manifest_parcel_status' => 'VALIDATED', 'updated_at' => now(),
                ] : [
                    'manifest_parcel_status' => 'FAILED', 'failure_code' => $eligibility['reason_code'],
                    'failure_reason' => $eligibility['presentation']['detail']['en'], 'active_slot' => null,
                    'processed_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('manifests')->where('manifest_id', $id)->update(['state' => 'OPEN', 'version' => $m->version + 1, 'updated_at' => now()]);
            $this->audit->write($actor->hqId, $actor->userId, 'MANIFEST_VALIDATED', 'MANIFEST', $id, $correlationId);
        });
        return $this->detail($actor, $nodeId, $id, $context);
    }

    /** @return array<string, mixed> */
    public function confirm(AuthenticatedPrincipal $actor, string $nodeId, string $id, int $expected, string $correlationId, ?string $reasonCode = null, ?string $description = null): array
    {
        $context = $this->access($actor, $nodeId, 'manifest.approve');
        $this->orchestration->confirm($actor,$nodeId,$id,$expected,$correlationId,$reasonCode,$description);
        return $this->detail($actor, $nodeId, $id, $context);
    }

    public function exception(AuthenticatedPrincipal $actor,string $nodeId,string $id):array{return $this->orchestration->exceptionState($actor,$nodeId,$id);}
    public function approveException(AuthenticatedPrincipal $actor,string $nodeId,string $id,int $manifestVersion,int $exceptionVersion,?string $reason,string $correlationId):array{$context=$this->access($actor,$nodeId,'manifest.approve');$this->orchestration->approve($actor,$nodeId,$id,$manifestVersion,$exceptionVersion,$reason,$correlationId);return$this->detail($actor,$nodeId,$id,$context);}
    public function rejectException(AuthenticatedPrincipal $actor,string $nodeId,string $id,int $manifestVersion,int $exceptionVersion,string $reason,string $correlationId):array{$context=$this->access($actor,$nodeId,'manifest.approve');$this->orchestration->reject($actor,$nodeId,$id,$manifestVersion,$exceptionVersion,$reason,$correlationId);return$this->detail($actor,$nodeId,$id,$context);}
    public function resubmitException(AuthenticatedPrincipal $actor,string $nodeId,string $id,int $manifestVersion,int $exceptionVersion,string $code,string $description,string $correlationId):array{$context=$this->access($actor,$nodeId,'manifest.edit');$this->orchestration->resubmit($actor,$nodeId,$id,$manifestVersion,$exceptionVersion,$code,$description,$correlationId);return$this->detail($actor,$nodeId,$id,$context);}

    /** @return array<string, mixed> */
    public function listItem(object|array $row): array
    {
        $r = (array) $row; $counts = $this->counts((string) $r['manifest_id']);
        return [
            'manifest_id' => (string) $r['manifest_id'], 'manifest_number' => (string) $r['manifest_number'],
            'node_id' => (string) $r['node_id'], 'manifest_status' => (string) $r['manifest_status'],
            'state' => (string) $r['state'], 'manifest_type' => $r['manifest_type'] === null ? $this->manifestType((string) $r['manifest_status']) : (string) $r['manifest_type'],
            'operational_context_type' => (string) $r['operational_context_type'],
            'context_key' => (string) $r['context_key'],
            'origin_node_id' => $r['origin_node_id'], 'destination_node_id' => $r['destination_node_id'],
            'route_plan_id' => $r['route_plan_id'], 'route_plan_leg_id' => $r['route_plan_leg_id'],
            'assigned_driver_id' => $r['assigned_driver_id'], 'assigned_vehicle_id' => $r['assigned_vehicle_id'],
            'version' => (int) $r['version'], 'total_count' => array_sum($counts),
            'succeeded_count' => $counts['succeeded'], 'failed_count' => $counts['failed'],
            'created_at' => $this->time($r['created_at']), 'updated_at' => $this->time($r['updated_at']),
            'context' => $this->operationalContext->summary((object) $r),
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
            'mp.*', 'p.parcel_number', 'p.current_status', 'p.current_node_id',
            'p.current_custody_type', 'p.current_custodian_id', 'p.active_route_plan_id',
            'p.active_route_plan_leg_id', 'p.version as parcel_version',
            'c.consignment_id', 'c.consignment_number', 'c.receiver_contact_name',
        ])->map(fn ($r): array => [
            'manifest_parcel_id' => (string) $r->manifest_parcel_id, 'parcel_id' => (string) $r->parcel_id,
            'parcel_number' => (string) $r->parcel_number, 'consignment_id' => (string) $r->consignment_id,
            'consignment_number' => (string) $r->consignment_number, 'receiver_contact_name' => (string) $r->receiver_contact_name,
            'current_status' => (string) $r->current_status, 'manifest_parcel_status' => (string) $r->manifest_parcel_status,
            'failure_code' => $r->failure_code, 'failure_reason' => $r->failure_reason,
            'eligibility' => $r->failure_code !== null
                ? ManifestEligibilityReason::metadata((string) $r->failure_code)
                : ($r->manifest_parcel_status === 'SUCCEEDED'
                    ? ManifestEligibilityReason::metadata(ManifestEligibilityReason::Eligible)
                    : $this->eligibility->evaluate($r, $m, $nodeId)),
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
        $exceptionState=in_array((string)$m->manifest_status,['NPU','NOK'],true)?$this->orchestration->exceptionState($actor,$nodeId,$id):null;
        return [...$base, 'created_by' => (string) $m->created_by, 'approved_by' => $m->approved_by,
            'closed_at' => $m->closed_at ? $this->time($m->closed_at) : null, 'parcels' => $parcels,
            'bucket_counts' => $this->counts($id), 'permitted_actions' => $actions,
            'status_events' => $this->statusEvents((string) $m->hq_id, $id), 'custody_events'=>$this->orchestration->custodyEvents((string)$m->hq_id,$id), 'movement_evidence'=>$this->orchestration->movementEvidence($m), 'exception_state'=>$exceptionState,
            'timeline' => $this->timeline((string) $m->hq_id, $id)];
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

    /** @param array<string,mixed> $context */
    private function assertDriverVisibility(array $context, string $target): void
    {
        if (in_array($target, ['PD', 'OD', 'OS'], true)
            && ! in_array('driver.view', $context['permissions'], true)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        }
    }

    /** @return list<array<string, mixed>> */
    private function statusEvents(string $hqId, string $manifestId): array
    {
        return DB::table('consignment_status_events as se')
            ->join('consignments as c', function ($join): void {
                $join->on('c.consignment_id', '=', 'se.consignment_id')->on('c.hq_id', '=', 'se.hq_id');
            })
            ->leftJoin('parcels as p', function ($join): void {
                $join->on('p.parcel_id', '=', 'se.parcel_id')->on('p.hq_id', '=', 'se.hq_id');
            })
            ->leftJoin('nodes as n', function ($join): void {
                $join->on('n.node_id', '=', 'se.node_id')->on('n.hq_id', '=', 'se.hq_id');
            })
            ->leftJoin('users as u', 'u.user_id', '=', 'se.initiator_id')
            ->where(['se.hq_id' => $hqId, 'se.manifest_id' => $manifestId])
            ->orderBy('se.event_sequence')->orderBy('se.created_at')
            ->get([
                'se.status_event_id', 'se.event_sequence', 'se.consignment_id', 'c.consignment_number',
                'se.parcel_id', 'p.parcel_number', 'se.previous_status', 'se.new_status',
                'se.reason_code', 'se.created_at', 'se.initiator_id', 'u.display_name as initiator_name',
                'se.node_id', 'n.node_title',
            ])->map(fn (object $event): array => [
                'status_event_id' => (string) $event->status_event_id,
                'event_sequence' => $event->event_sequence === null ? null : (int) $event->event_sequence,
                'consignment_id' => (string) $event->consignment_id,
                'consignment_number' => (string) $event->consignment_number,
                'parcel_id' => $event->parcel_id,
                'parcel_number' => $event->parcel_number,
                'previous_status' => (string) $event->previous_status,
                'new_status' => (string) $event->new_status,
                'reason_code' => $event->reason_code,
                'initiator_id' => (string) $event->initiator_id,
                'initiator_name' => (string) ($event->initiator_name ?? ''),
                'node_id' => $event->node_id,
                'node_title' => $event->node_title,
                'created_at' => $this->time($event->created_at),
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function timeline(string $hqId, string $manifestId): array
    {
        return DB::table('audit_events as ae')
            ->leftJoin('users as u', 'u.user_id', '=', 'ae.initiator_id')
            ->where([
                'ae.hq_id' => $hqId,
                'ae.target_type' => 'MANIFEST',
                'ae.target_id' => $manifestId,
            ])->orderBy('ae.created_at')->orderBy('ae.audit_id')
            ->get(['ae.audit_id', 'ae.action_key', 'ae.initiator_id', 'u.display_name as initiator_name', 'ae.correlation_id', 'ae.created_at'])
            ->map(fn (object $event): array => [
                'audit_id' => (string) $event->audit_id,
                'action_key' => (string) $event->action_key,
                'initiator_id' => $event->initiator_id,
                'initiator_name' => (string) ($event->initiator_name ?? ''),
                'correlation_id' => (string) $event->correlation_id,
                'created_at' => $this->time($event->created_at),
            ])->all();
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
        if ((int) $m->version !== $expected) throw new ApiException(ApiErrorCode::ManifestVersionConflict, 409, 'The Manifest version is stale.', details: ['current_version' => (int) $m->version]);
    }

    /** @return list<object> */
    private function resolveInput(AuthenticatedPrincipal $actor, string $nodeId, string $identifier): array
    {
        return DB::table('parcels as p')->join('consignments as c', function ($j): void {
            $j->on('c.consignment_id', '=', 'p.consignment_id')->on('c.hq_id', '=', 'p.hq_id');
        })->where('p.hq_id', $actor->hqId)->where(fn ($q) => $this->visibleParcelAtNode($q, $nodeId))
            ->where(fn ($q) => $q->where('p.parcel_number', $identifier)->orWhere('c.consignment_number', $identifier))
            ->select(['p.*'])->orderBy('p.parcel_number')->get()->all();
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

    private function manifestType(string $target): string{return match($target){'PD'=>'PICKUP_ASSIGNMENT','PU'=>'PICKUP_COMPLETION','NPU'=>'PICKUP_EXCEPTION','IR'=>'INBOUND_RECEPTION','ROU'=>'ROUTE_REGISTRATION','OF'=>'OUTBOUND_TRANSFER','OS'=>'LINEHAUL_DEPARTURE','OD'=>'DELIVERY_ASSIGNMENT','OK'=>'DELIVERY_COMPLETION','NOK'=>'DELIVERY_EXCEPTION'};}

    private function visibleParcelAtNode($query, string $nodeId): void
    {
        $query->where('p.current_node_id', $nodeId)
            ->orWhereExists(fn ($q) => $q->selectRaw('1')->from('route_plan_legs as rpl')->whereColumn('rpl.route_plan_leg_id', 'p.active_route_plan_leg_id')->where('rpl.destination_node_id', $nodeId)->where('rpl.status', 'IN_TRANSIT'))
            ->orWhereExists(fn ($q) => $q->selectRaw('1')->from('pickup_tasks as pt')->whereColumn('pt.consignment_id', 'p.consignment_id')->where('pt.node_id', $nodeId)->whereIn('pt.status', ['PENDING', 'ASSIGNED', 'IN_PROGRESS']))
            ->orWhereExists(fn ($q) => $q->selectRaw('1')->from('delivery_tasks as dt')->whereColumn('dt.consignment_id', 'p.consignment_id')->where('dt.node_id', $nodeId)->whereIn('dt.status', ['PENDING', 'ASSIGNED', 'IN_PROGRESS']))
            ->orWhere('c.pickup_node_id', $nodeId)->orWhere('c.delivery_node_id', $nodeId);
    }
}
