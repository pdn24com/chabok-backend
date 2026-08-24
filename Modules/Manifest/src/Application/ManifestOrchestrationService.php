<?php

declare(strict_types=1);

namespace Modules\Manifest\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Consignment\Application\ConsignmentAggregateProjector;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Manifest\Domain\ManifestEligibilityReason as Reason;
use Modules\Operations\Application\CoveragePolicyService;
use Modules\Operations\Application\DeliveryTaskService;
use Modules\Operations\Application\RouteDefinitionService;

final readonly class ManifestOrchestrationService
{
    public function __construct(private AuthorizationContextResolver $authorization,private TransactionManager $transactions,private AuditWriter $audit,private OutboxWriter $outbox,private ManifestEligibilityEvaluator $eligibility,private DeliveryTaskService $deliveryTasks,private CoveragePolicyService $coverage,private RouteDefinitionService $routes,private ConsignmentAggregateProjector $aggregates){}

    public function confirm(AuthenticatedPrincipal $actor,string $node,string $id,int $expected,string $correlationId,?string $reasonCode=null,?string $description=null):void
    {
        $this->access($actor,$node,'manifest.approve');
        $result=$this->transactions->run(function()use($actor,$node,$id,$expected,$correlationId,$reasonCode,$description):int{
            $m=$this->lockedManifest($actor,$node,$id);$this->manifestVersion($m,$expected);if((string)$m->state!=='OPEN')throw new ApiException(ApiErrorCode::ManifestNotEditable,422,'The Manifest must be Open before confirmation.');
            if(in_array((string)$m->manifest_status,['NPU','NOK'],true)){if(trim((string)$reasonCode)===''||trim((string)$description)==='')throw new ApiException(ApiErrorCode::ValidationError,422,'Exception reason code and description are required.');return $this->submitException($actor,$node,$m,trim((string)$reasonCode),trim((string)$description),$correlationId);}
            if(in_array((string)$m->manifest_status,['PD','OD','OS'],true)){
                $this->requirePermission($actor,'driver.view');
                $this->requirePermission($actor,'driver.assign');
            }
            $success=$this->applyRows($actor,$node,$m,$correlationId,false);
            if($success===0){DB::table('manifests')->where('manifest_id',$id)->update(['version'=>(int)$m->version+1,'updated_at'=>now()]);return 0;}
            $now=now();DB::table('manifests')->where('manifest_id',$id)->update(['state'=>'CLOSED','approved_by'=>$actor->userId,'closed_at'=>$now,'operation_recorded_at'=>$now,'version'=>(int)$m->version+1,'updated_at'=>$now]);
            $this->audit->write($actor->hqId,$actor->userId,'MANIFEST_CONFIRMED','MANIFEST',$id,$correlationId);
            $this->outbox->write($actor->hqId,'MANIFEST',$id,'manifest.closed',$correlationId,['manifest_id'=>$id,'manifest_status'=>(string)$m->manifest_status,'succeeded_count'=>(string)$success]);return $success;
        });
        if($result===0)throw new ApiException(ApiErrorCode::ManifestNoSuccessfulParcels,422,'No Parcel can be confirmed.',details:['current_version'=>$expected+1]);
    }

    public function approve(AuthenticatedPrincipal $actor,string $node,string $id,int $manifestVersion,int $exceptionVersion,?string $reason,string $correlationId):void
    {
        $this->reviewAccess($actor,$node);
        $result=$this->transactions->run(function()use($actor,$node,$id,$manifestVersion,$exceptionVersion,$reason,$correlationId):int{
            $m=$this->lockedManifest($actor,$node,$id);$this->manifestVersion($m,$manifestVersion);$case=$this->lockedPendingCase($actor,$id);$this->exceptionVersion($case,$exceptionVersion);$this->differentReviewer($actor,$case);
            $success=$this->applyRows($actor,$node,$m,$correlationId,true);if($success===0)return 0;
            $next=(int)$case->version+1;DB::table('operational_exception_cases')->where('exception_case_id',$case->exception_case_id)->update(['case_status'=>'APPROVED','reviewed_by'=>$actor->userId,'reviewed_at'=>now(),'decision_note'=>$reason,'resolution_action'=>'ESCALATE','version'=>$next,'active_pending_slot'=>null,'updated_at'=>now()]);
            $this->exceptionHistory($actor,$case,'APPROVED',$reason,$manifestVersion+1,$next);
            DB::table('manifests')->where('manifest_id',$id)->update(['state'=>'CLOSED','approved_by'=>$actor->userId,'closed_at'=>now(),'operation_recorded_at'=>now(),'version'=>$manifestVersion+1,'updated_at'=>now()]);
            $this->audit->write($actor->hqId,$actor->userId,'MANIFEST_EXCEPTION_APPROVED','MANIFEST',$id,$correlationId);
            $this->outbox->write($actor->hqId,'MANIFEST',$id,'manifest.closed',$correlationId,['manifest_id'=>$id,'manifest_status'=>(string)$m->manifest_status,'succeeded_count'=>(string)$success]);return$success;
        });
        if($result===0)throw new ApiException(ApiErrorCode::ManifestNoSuccessfulParcels,422,'No Parcel remains eligible for Exception approval.');
    }

    public function reject(AuthenticatedPrincipal $actor,string $node,string $id,int $manifestVersion,int $exceptionVersion,string $reason,string $correlationId):void
    {
        $this->reviewAccess($actor,$node);$this->transactions->run(function()use($actor,$node,$id,$manifestVersion,$exceptionVersion,$reason,$correlationId):void{$m=$this->lockedManifest($actor,$node,$id);$this->manifestVersion($m,$manifestVersion);$case=$this->lockedPendingCase($actor,$id);$this->exceptionVersion($case,$exceptionVersion);$this->differentReviewer($actor,$case);$next=(int)$case->version+1;DB::table('operational_exception_cases')->where('exception_case_id',$case->exception_case_id)->update(['case_status'=>'REJECTED','reviewed_by'=>$actor->userId,'reviewed_at'=>now(),'decision_note'=>$reason,'resolution_action'=>'NO_CHANGE','version'=>$next,'active_pending_slot'=>null,'updated_at'=>now()]);$this->exceptionHistory($actor,$case,'REJECTED',$reason,$manifestVersion+1,$next);DB::table('manifests')->where('manifest_id',$id)->update(['version'=>$manifestVersion+1,'updated_at'=>now()]);$this->audit->write($actor->hqId,$actor->userId,'MANIFEST_EXCEPTION_REJECTED','MANIFEST',$id,$correlationId);$this->commandEvent($actor,$id,(string)$case->consignment_id,'MANIFEST_EXCEPTION_REJECTED','REJECTED',$correlationId);});
    }

    public function resubmit(AuthenticatedPrincipal $actor,string $node,string $id,int $manifestVersion,int $exceptionVersion,string $code,string $description,string $correlationId):void
    {
        $this->access($actor,$node,'manifest.edit');$this->transactions->run(function()use($actor,$node,$id,$manifestVersion,$exceptionVersion,$code,$description,$correlationId):void{$m=$this->lockedManifest($actor,$node,$id);$this->manifestVersion($m,$manifestVersion);$prior=DB::table('operational_exception_cases')->where(['hq_id'=>$actor->hqId,'manifest_id'=>$id])->orderByDesc('submission_sequence')->lockForUpdate()->first();if($prior===null||(string)$prior->case_status!=='REJECTED')throw new ApiException(ApiErrorCode::ExceptionAlreadyDecided,422,'Only a rejected Exception may be resubmitted.');$this->exceptionVersion($prior,$exceptionVersion);$sequence=(int)$prior->submission_sequence+1;$caseId=$this->insertException($actor,$m,$sequence,$code,$description);$new=DB::table('operational_exception_cases')->where('exception_case_id',$caseId)->first();$this->exceptionHistory($actor,$new,'RESUBMITTED',$description,$manifestVersion+1,1);DB::table('manifests')->where('manifest_id',$id)->update(['version'=>$manifestVersion+1,'updated_at'=>now()]);$this->audit->write($actor->hqId,$actor->userId,'MANIFEST_EXCEPTION_RESUBMITTED','MANIFEST',$id,$correlationId);$this->commandEvent($actor,$id,(string)$new->consignment_id,'MANIFEST_EXCEPTION_RESUBMITTED','PENDING',$correlationId);});
    }

    /** @return array<string,mixed> */
    public function exceptionState(AuthenticatedPrincipal $actor,string $node,string $id):array
    {
        $this->access($actor,$node,'manifest.view');$m=DB::table('manifests')->where(['hq_id'=>$actor->hqId,'node_id'=>$node,'manifest_id'=>$id])->first();if($m===null)throw new ApiException(ApiErrorCode::ResourceNotFound,404,'Resource not found.');$cases=DB::table('operational_exception_cases')->where(['hq_id'=>$actor->hqId,'manifest_id'=>$id])->orderByDesc('submission_sequence')->get();$mapped=$cases->map(fn(object $c):array=>$this->caseResource($c))->all();return['manifest_id'=>$id,'manifest_state'=>(string)$m->state,'manifest_version'=>(int)$m->version,'current_exception'=>$mapped[0]??null,'previous_attempts'=>array_slice($mapped,1)];
    }

    /** @return list<array<string,mixed>> */
    public function custodyEvents(string $hq,string $manifest):array{return DB::table('parcel_custody_events')->where(['hq_id'=>$hq,'manifest_id'=>$manifest])->orderBy('event_sequence')->get()->map(fn(object $e):array=>['custody_event_id'=>(string)$e->custody_event_id,'consignment_id'=>(string)$e->consignment_id,'parcel_id'=>(string)$e->parcel_id,'from_node_id'=>$e->from_node_id,'to_node_id'=>$e->to_node_id,'from_custody_type'=>$e->from_custody_type,'to_custody_type'=>(string)$e->to_custody_type,'initiator_id'=>(string)$e->initiator_id,'created_at'=>(string)$e->created_at])->all();}
    /** @return array<string,mixed>|null */
    public function movementEvidence(object $m):?array{if(!in_array((string)$m->manifest_status,['ROU','OF','OS','CI','IR'],true))return null;return['target_status'=>(string)$m->manifest_status,'context_key'=>(string)$m->context_key,'operational_context_type'=>(string)$m->operational_context_type,'origin_node_id'=>$m->origin_node_id,'destination_node_id'=>$m->destination_node_id,'route_plan_id'=>$m->route_plan_id,'route_plan_leg_id'=>$m->route_plan_leg_id,'route_definition_version_id'=>$m->route_definition_version_id,'route_definition_version_leg_id'=>$m->route_definition_version_leg_id,'driver_id'=>$m->assigned_driver_id,'vehicle_id'=>$m->assigned_vehicle_id,'source_manifest_id'=>$m->source_manifest_id,'recorded_at'=>$m->operation_recorded_at??$m->closed_at];}

    private function submitException(AuthenticatedPrincipal $actor,string $node,object $m,string $code,string $description,string $correlationId):int
    {
        if(DB::table('operational_exception_cases')->where(['hq_id'=>$actor->hqId,'manifest_id'=>$m->manifest_id,'case_status'=>'PENDING'])->exists())throw new ApiException(ApiErrorCode::ExceptionReviewRequired,422,'A pending Exception already exists.');$eligible=$this->eligibleRows($actor,$node,$m);if($eligible===[])return 0;$caseId=$this->insertException($actor,$m,1,$code,$description);$case=DB::table('operational_exception_cases')->where('exception_case_id',$caseId)->first();$this->exceptionHistory($actor,$case,'SUBMITTED',$description,(int)$m->version+1,1);DB::table('manifests')->where('manifest_id',$m->manifest_id)->update(['version'=>(int)$m->version+1,'updated_at'=>now()]);$this->audit->write($actor->hqId,$actor->userId,'MANIFEST_EXCEPTION_SUBMITTED','MANIFEST',(string)$m->manifest_id,$correlationId);$this->commandEvent($actor,(string)$m->manifest_id,(string)$case->consignment_id,'MANIFEST_EXCEPTION_SUBMITTED','PENDING',$correlationId);return count($eligible);
    }

    private function insertException(AuthenticatedPrincipal $actor,object $m,int $sequence,string $code,string $description):string
    {
        $first=DB::table('manifest_parcels as mp')->join('parcels as p','p.parcel_id','=','mp.parcel_id')->where(['mp.manifest_id'=>$m->manifest_id])->orderBy('mp.created_at')->first(['p.consignment_id','p.parcel_id']);if($first===null)throw new ApiException(ApiErrorCode::ManifestNoSuccessfulParcels,422,'The Manifest has no Parcels.');$id=(string)Str::uuid();DB::table('operational_exception_cases')->insert(['exception_case_id'=>$id,'hq_id'=>$actor->hqId,'exception_type'=>(string)$m->manifest_status,'manifest_id'=>$m->manifest_id,'submission_sequence'=>$sequence,'consignment_id'=>$first->consignment_id,'parcel_id'=>null,'driver_id'=>$m->assigned_driver_id,'submitted_by'=>$actor->userId,'reason_code'=>$code,'description'=>$description,'case_status'=>'PENDING','version'=>1,'active_pending_slot'=>hash('sha256',$actor->hqId.'|'.$m->manifest_id.'|PENDING'),'created_at'=>now(),'updated_at'=>now()]);return$id;
    }

    private function applyRows(AuthenticatedPrincipal $actor,string $node,object $m,string $correlationId,bool $exceptionApproval):int
    {
        $rows=DB::table('manifest_parcels')->where(['hq_id'=>$actor->hqId,'manifest_id'=>$m->manifest_id])->whereIn('manifest_parcel_status',['PENDING','VALIDATED','FAILED'])->orderBy('parcel_id')->lockForUpdate()->get();$success=0;$consignments=[];$eligible=[];
        foreach($rows as $row){$p=DB::table('parcels')->where(['hq_id'=>$actor->hqId,'parcel_id'=>$row->parcel_id])->lockForUpdate()->first();$e=$p? $this->eligibility->evaluate($p,$m,$node):Reason::metadata(Reason::ParcelNotFound);if(!$e['eligible']){$this->failRow($row,$e);continue;}$eligible[]=['row'=>$row,'parcel'=>$p];}
        if(in_array((string)$m->manifest_status,['NPU','NOK'],true)&&!$exceptionApproval)return count($eligible);
        foreach($eligible as $item){$row=$item['row'];$p=$item['parcel'];$evidence=$this->applyTarget($actor,$node,$m,$p,$correlationId);$this->recordTransition($actor,$node,$m,$p,$evidence);DB::table('manifest_parcels')->where('manifest_parcel_id',$row->manifest_parcel_id)->update(['manifest_parcel_status'=>'SUCCEEDED','failure_code'=>null,'failure_reason'=>null,'active_slot'=>null,'source_status'=>$p->current_status,'origin_node_id'=>$evidence['origin_node_id'],'destination_node_id'=>$evidence['destination_node_id'],'route_plan_id'=>$evidence['route_plan_id'],'route_definition_version_id'=>$evidence['route_definition_version_id'],'route_plan_leg_id'=>$evidence['route_plan_leg_id'],'route_definition_version_leg_id'=>$evidence['route_definition_version_leg_id'],'assigned_driver_id'=>$evidence['assigned_driver_id'],'assigned_vehicle_id'=>$evidence['assigned_vehicle_id'],'processed_at'=>now(),'evidence_recorded_at'=>now(),'updated_at'=>now()]);$success++;$consignments[]=(string)$p->consignment_id;}
        foreach(array_unique($consignments) as $consignment)$this->aggregates->project($actor,$consignment,(string)$m->manifest_status,$node,(string)$m->manifest_id,'MANIFEST_AGGREGATE_PROJECTED',$m->assigned_driver_id);
        if($success>0&&$m->manifest_status==='OS'){DB::table('drivers')->where(['hq_id'=>$actor->hqId,'driver_id'=>$m->assigned_driver_id,'availability_status'=>'AVAILABLE'])->update(['availability_status'=>'ON_MISSION','updated_at'=>now()]);DB::table('vehicles')->where(['hq_id'=>$actor->hqId,'vehicle_id'=>$m->assigned_vehicle_id,'availability_status'=>'AVAILABLE'])->update(['availability_status'=>'ON_MISSION','updated_at'=>now()]);}
        if($success>0&&in_array((string)$m->manifest_status,['CI','IR'],true)&&$m->operational_context_type!=='PICKUP_RECEPTION'){DB::table('drivers')->where(['hq_id'=>$actor->hqId,'driver_id'=>$m->assigned_driver_id,'availability_status'=>'ON_MISSION'])->update(['availability_status'=>'AVAILABLE','updated_at'=>now()]);DB::table('vehicles')->where(['hq_id'=>$actor->hqId,'vehicle_id'=>$m->assigned_vehicle_id,'availability_status'=>'ON_MISSION'])->update(['availability_status'=>'AVAILABLE','updated_at'=>now()]);}
        return$success;
    }

    /** @return list<object> */
    private function eligibleRows(AuthenticatedPrincipal $actor,string $node,object $m):array{$out=[];foreach(DB::table('manifest_parcels')->where(['hq_id'=>$actor->hqId,'manifest_id'=>$m->manifest_id])->orderBy('parcel_id')->lockForUpdate()->get() as $row){$p=DB::table('parcels')->where(['hq_id'=>$actor->hqId,'parcel_id'=>$row->parcel_id])->lockForUpdate()->first();$e=$p?$this->eligibility->evaluate($p,$m,$node):Reason::metadata(Reason::ParcelNotFound);if($e['eligible']){$out[]=$p;DB::table('manifest_parcels')->where('manifest_parcel_id',$row->manifest_parcel_id)->update(['manifest_parcel_status'=>'VALIDATED','failure_code'=>null,'failure_reason'=>null,'processed_at'=>null,'updated_at'=>now()]);}else $this->failRow($row,$e);}return$out;}
    /** @param array<string,mixed> $e */
    private function failRow(object $row,array $e):void{DB::table('manifest_parcels')->where('manifest_parcel_id',$row->manifest_parcel_id)->update(['manifest_parcel_status'=>'FAILED','failure_code'=>$e['reason_code'],'failure_reason'=>$e['presentation']['detail']['en'],'active_slot'=>null,'processed_at'=>now(),'updated_at'=>now()]);}

    /** @return array<string,mixed> */
    private function applyTarget(AuthenticatedPrincipal $actor,string $node,object $m,object $p,string $correlationId):array
    {
        $target = (string) $m->manifest_status;
        $route = [
            'route_plan_id' => $m->route_plan_id,
            'route_definition_version_id' => $m->route_definition_version_id,
            'route_plan_leg_id' => $m->route_plan_leg_id,
            'route_definition_version_leg_id' => $m->route_definition_version_leg_id,
        ];
        $activeRoutePlanId = $route['route_plan_id'] ?? $p->active_route_plan_id;
        $activeRouteLegId = $route['route_plan_leg_id'] ?? $p->active_route_plan_leg_id;

        if ($target === 'ROU') {
            $route = $this->ensureRoute($actor, $node, $p, $correlationId);
            $activeRoutePlanId = $route['route_plan_id'];
            $activeRouteLegId = $route['route_plan_leg_id'];
        }
        if ($target === 'OS') {
            $leg = DB::table('route_plan_legs as l')
                ->join('route_plans as plan', function ($join): void {
                    $join->on('plan.route_plan_id', '=', 'l.route_plan_id')
                        ->on('plan.hq_id', '=', 'l.hq_id');
                })
                ->where(['l.hq_id' => $actor->hqId, 'l.route_plan_leg_id' => $p->active_route_plan_leg_id])
                ->whereIn('l.status', ['OUTBOUND_CONFIRMED', 'IN_TRANSIT'])
                ->lockForUpdate()
                ->first(['l.*', 'plan.route_definition_version_id']);
            if ($leg === null) {
                throw new ApiException(ApiErrorCode::RouteLegNotReady, 422, 'The Route Leg is not ready for departure.');
            }
            $route = [
                'route_plan_id' => (string) $leg->route_plan_id,
                'route_definition_version_id' => (string) $leg->route_definition_version_id,
                'route_plan_leg_id' => (string) $leg->route_plan_leg_id,
                'route_definition_version_leg_id' => (string) $leg->source_route_definition_version_leg_id,
            ];
            $activeRoutePlanId = $route['route_plan_id'];
            $activeRouteLegId = $route['route_plan_leg_id'];
            if ((string) $leg->status === 'OUTBOUND_CONFIRMED') {
                DB::table('route_plan_legs')->where([
                    'hq_id' => $actor->hqId,
                    'route_plan_leg_id' => $leg->route_plan_leg_id,
                ])->update(['status' => 'IN_TRANSIT', 'updated_at' => now()]);
            }
        }
        if (in_array($target, ['IR', 'CI'], true) && $p->current_status === 'OS') {
            $source = DB::table('manifest_parcels')->where([
                'hq_id' => $actor->hqId,
                'manifest_id' => $m->source_manifest_id,
                'parcel_id' => $p->parcel_id,
                'manifest_parcel_status' => 'SUCCEEDED',
            ])->first();
            if ($source === null) {
                throw new ApiException(ApiErrorCode::RouteLegUnavailable, 422, 'The source movement evidence is unavailable.');
            }
            $route = [
                'route_plan_id' => $source->route_plan_id,
                'route_definition_version_id' => $source->route_definition_version_id,
                'route_plan_leg_id' => $source->route_plan_leg_id,
                'route_definition_version_leg_id' => $source->route_definition_version_leg_id,
            ];
            $currentLeg = DB::table('route_plan_legs')->where([
                'hq_id' => $actor->hqId,
                'route_plan_leg_id' => $source->route_plan_leg_id,
                'route_plan_id' => $source->route_plan_id,
            ])->lockForUpdate()->first();
            if ($currentLeg === null) {
                throw new ApiException(ApiErrorCode::RouteLegNotReady, 422, 'The source Route Leg is unavailable.');
            }
            DB::table('route_plan_legs')->where([
                'hq_id' => $actor->hqId,
                'route_plan_leg_id' => $source->route_plan_leg_id,
            ])->update(['status' => 'RECEIVED', 'received_at' => now(), 'updated_at' => now()]);
            $next = DB::table('route_plan_legs')->where([
                'hq_id' => $actor->hqId,
                'route_plan_id' => $source->route_plan_id,
                'leg_order' => (int) $currentLeg->leg_order + 1,
                'origin_node_id' => $node,
            ])->first();
            if ($target === 'CI') {
                if ($next === null) {
                    throw new ApiException(ApiErrorCode::RouteLegUnavailable, 422, 'No following published Route Leg is available.');
                }
                $activeRoutePlanId = $source->route_plan_id;
                $activeRouteLegId = $next->route_plan_leg_id;
            } else {
                $activeRoutePlanId = $source->route_plan_id;
                $activeRouteLegId = null;
                $hasUnreceivedLeg = DB::table('route_plan_legs')->where([
                    'hq_id' => $actor->hqId,
                    'route_plan_id' => $source->route_plan_id,
                ])->whereNot('status', 'RECEIVED')->exists();
                if (! $hasUnreceivedLeg) {
                    DB::table('route_plans')->where([
                        'hq_id' => $actor->hqId,
                        'route_plan_id' => $source->route_plan_id,
                    ])->update(['status' => 'COMPLETED', 'active_slot' => null, 'updated_at' => now()]);
                }
            }
        }
        if ($target === 'OF') {
            DB::table('route_plan_legs')->where([
                'hq_id' => $actor->hqId,
                'route_plan_leg_id' => $m->route_plan_leg_id,
            ])->whereIn('status', ['PENDING', 'ROUTED'])->update([
                'status' => 'OUTBOUND_CONFIRMED',
                'routed_at' => DB::raw('COALESCE(routed_at, CURRENT_TIMESTAMP(6))'),
                'updated_at' => now(),
            ]);
            $activeRoutePlanId = $m->route_plan_id;
            $activeRouteLegId = $m->route_plan_leg_id;
        }

        $custody = match ($target) {
            'PD', 'PU', 'NPU' => 'PICKUP_DRIVER',
            'OS' => 'LINEHAUL_DRIVER',
            'OD', 'NOK' => 'DELIVERY_DRIVER',
            'OK' => 'RECIPIENT',
            default => 'NODE',
        };
        $custodian = match ($custody) {
            'PICKUP_DRIVER', 'LINEHAUL_DRIVER', 'DELIVERY_DRIVER' => $m->assigned_driver_id,
            'NODE' => $node,
            default => null,
        };
        $nextNode = in_array($custody, ['PICKUP_DRIVER', 'LINEHAUL_DRIVER', 'DELIVERY_DRIVER', 'RECIPIENT'], true) ? null : $node;
        DB::table('parcels')->where([
            'hq_id' => $actor->hqId,
            'parcel_id' => $p->parcel_id,
        ])->update([
            'current_status' => $target,
            'current_node_id' => $nextNode,
            'current_custody_type' => $custody,
            'current_custodian_id' => $custodian,
            'active_route_plan_id' => $activeRoutePlanId,
            'active_route_plan_leg_id' => $activeRouteLegId,
            'version' => (int) $p->version + 1,
            'updated_at' => now(),
        ]);
        if ($target === 'PD') $this->pickupAssigned($actor, $node, $p, $m);
        if ($target === 'PU') $this->pickupCompleted($p);
        if ($target === 'NPU') $this->pickupFailed($p);
        if ($target === 'IR' && $p->current_status === 'PU') {
            $this->pickupReceived($p);
            if (DB::table('consignments')->where([
                'hq_id' => $actor->hqId,
                'consignment_id' => $p->consignment_id,
                'delivery_node_id' => $node,
            ])->exists()) {
                $this->deliveryTasks->ensurePending($actor, $node, (string) $p->consignment_id);
            }
        }
        if ($target === 'OD') {
            $this->deliveryTasks->ensurePending($actor, $node, (string) $p->consignment_id);
            $this->deliveryTasks->activateFromManifest($actor, $node, (string) $p->consignment_id, (string) $m->assigned_driver_id, (string) $m->manifest_id);
        }
        if ($target === 'OK') $this->deliveryCompleted($actor, $p);
        if ($target === 'NOK') $this->deliveryFailed($actor, $p);
        return['origin_node_id'=>$m->origin_node_id??$p->current_node_id,'destination_node_id'=>$m->destination_node_id??$nextNode,...$route,'assigned_driver_id'=>$m->assigned_driver_id,'assigned_vehicle_id'=>$m->assigned_vehicle_id];
    }

    /** @return array<string,mixed> */
    private function ensureRoute(AuthenticatedPrincipal $actor,string $node,object $p,string $correlationId):array
    {
        $plan=DB::table('route_plans')->where(['hq_id'=>$actor->hqId,'consignment_id'=>$p->consignment_id])->whereIn('status',['PLANNED','IN_PROGRESS'])->lockForUpdate()->first();if($plan===null)$plan=$this->createPlan($actor,$node,(string)$p->consignment_id,$correlationId);
        $leg=DB::table('route_plan_legs')->where(['hq_id'=>$actor->hqId,'route_plan_id'=>$plan->route_plan_id,'origin_node_id'=>$node])->whereIn('status',['PENDING','ROUTED'])->orderBy('leg_order')->lockForUpdate()->first();if($leg===null)throw new ApiException(ApiErrorCode::RouteLegUnavailable,422,'No next Route Leg is available.');if((int)$leg->leg_order>1&&!DB::table('route_plan_legs')->where(['route_plan_id'=>$plan->route_plan_id,'leg_order'=>(int)$leg->leg_order-1,'status'=>'RECEIVED'])->exists())throw new ApiException(ApiErrorCode::RouteLegNotReady,422,'The preceding Route Leg is not received.');if((string)$leg->status==='PENDING'){DB::table('route_plan_legs')->where('route_plan_leg_id',$leg->route_plan_leg_id)->update(['status'=>'ROUTED','routed_at'=>now(),'updated_at'=>now()]);DB::table('route_plans')->where('route_plan_id',$plan->route_plan_id)->update(['status'=>'IN_PROGRESS','version'=>(int)$plan->version+1,'updated_at'=>now()]);}return['route_plan_id'=>(string)$plan->route_plan_id,'route_definition_version_id'=>(string)$plan->route_definition_version_id,'route_plan_leg_id'=>(string)$leg->route_plan_leg_id,'route_definition_version_leg_id'=>(string)$leg->source_route_definition_version_leg_id];
    }

    private function createPlan(AuthenticatedPrincipal $actor,string $node,string $consignmentId,string $correlationId):object
    {
        $c=DB::table('consignments')->where(['hq_id'=>$actor->hqId,'consignment_id'=>$consignmentId,'pickup_node_id'=>$node])->lockForUpdate()->first();if($c===null)throw new ApiException(ApiErrorCode::RoutePlanUnavailable,422,'The Consignment cannot resolve a Route Plan at this Node.');$input=[];if($c->receiver_city_id!==null){$input['city_id']=(string)$c->receiver_city_id;$province=DB::table('cities')->where(['city_id'=>$c->receiver_city_id,'is_active'=>true])->value('province_id');if($province)$input['province_id']=(string)$province;}if(preg_match('/^\d{10}$/',(string)$c->receiver_postal_code)===1)$input['postal_code']=(string)$c->receiver_postal_code;if($c->receiver_latitude!==null&&$c->receiver_longitude!==null){$input['latitude']=(float)$c->receiver_latitude;$input['longitude']=(float)$c->receiver_longitude;}if($input===[])throw new ApiException(ApiErrorCode::CoverageNotFound,422,'Canonical destination geography is unavailable.');$at=now();$coverage=$this->coverage->resolve((string)$actor->hqId,'DESTINATION_GATEWAY',$input,$c->service_offering_version_id,$at);$route=$this->routes->resolve((string)$actor->hqId,'TRUNK',$node,(string)$coverage['target_node_id'],$c->service_offering_version_id,$at);$legs=DB::table('route_definition_version_legs')->where(['hq_id'=>$actor->hqId,'route_definition_version_id'=>$route['route_definition_version_id']])->orderBy('leg_order')->get();if($legs->isEmpty())throw new ApiException(ApiErrorCode::ConfigVersionUnavailable,422,'The published Route Version is unavailable.');$id=(string)Str::uuid();DB::table('route_plans')->insert(['route_plan_id'=>$id,'hq_id'=>$actor->hqId,'consignment_id'=>$consignmentId,'route_definition_id'=>$route['route_definition_id'],'route_definition_version_id'=>$route['route_definition_version_id'],'status'=>'PLANNED','active_slot'=>hash('sha256',$actor->hqId.'|'.$consignmentId.'|ACTIVE'),'version'=>1,'created_by'=>$actor->userId,'created_at'=>$at,'updated_at'=>$at]);$ordered=[];foreach($legs as $leg){$legacy=DB::table('route_definition_legs')->where(['hq_id'=>$actor->hqId,'route_definition_id'=>$route['route_definition_id'],'leg_order'=>$leg->leg_order,'status'=>'ACTIVE'])->value('route_definition_leg_id');if($legacy===null)throw new ApiException(ApiErrorCode::ConfigVersionUnavailable,422,'Published Route Leg evidence is unavailable.');DB::table('route_plan_legs')->insert(['route_plan_leg_id'=>(string)Str::uuid(),'hq_id'=>$actor->hqId,'route_plan_id'=>$id,'source_route_definition_leg_id'=>$legacy,'source_route_definition_version_leg_id'=>$leg->route_definition_version_leg_id,'leg_order'=>$leg->leg_order,'origin_node_id'=>$leg->origin_node_id,'destination_node_id'=>$leg->destination_node_id,'status'=>'PENDING','created_at'=>$at,'updated_at'=>$at]);$ordered[]=['route_definition_version_leg_id'=>(string)$leg->route_definition_version_leg_id,'leg_order'=>(int)$leg->leg_order,'origin_node_id'=>(string)$leg->origin_node_id,'destination_node_id'=>(string)$leg->destination_node_id];}$matched=(array)$coverage['matched_evidence'];DB::table('route_plan_resolution_evidence')->insert(['resolution_evidence_id'=>(string)Str::uuid(),'hq_id'=>$actor->hqId,'consignment_id'=>$consignmentId,'route_plan_id'=>$id,'coverage_policy_id'=>$coverage['coverage_policy_id'],'coverage_policy_version_id'=>$coverage['coverage_policy_version_id'],'coverage_rule_id'=>$coverage['coverage_rule_id'],'coverage_criterion_type'=>$coverage['criterion_type'],'coverage_priority'=>$coverage['priority'],'resolution_input'=>json_encode($coverage['input'],JSON_THROW_ON_ERROR),'matched_geography_evidence'=>isset($matched['geography'])?json_encode($matched['geography'],JSON_THROW_ON_ERROR):null,'matched_postal_evidence'=>isset($matched['postal'])?json_encode($matched['postal'],JSON_THROW_ON_ERROR):null,'matched_geometry_evidence'=>isset($matched['geometry'])?json_encode($matched['geometry'],JSON_THROW_ON_ERROR):null,'destination_gateway_node_id'=>$coverage['target_node_id'],'route_definition_id'=>$route['route_definition_id'],'route_definition_version_id'=>$route['route_definition_version_id'],'route_purpose'=>'TRUNK','ordered_route_legs'=>json_encode($ordered,JSON_THROW_ON_ERROR),'offering_version_id'=>$c->service_offering_version_id,'resolved_at'=>$at,'created_at'=>$at]);$this->audit->write($actor->hqId,$actor->userId,'ROUTE_PLAN_CREATED','ROUTE_PLAN',$id,$correlationId);$this->commandEvent($actor,$id,$consignmentId,'ROUTE_PLAN_CREATED','PLANNED',$correlationId);return DB::table('route_plans')->where('route_plan_id',$id)->first();
    }

    /** @param array<string,mixed> $e */
    private function recordTransition(AuthenticatedPrincipal $actor,string $node,object $m,object $p,array $e):void{$this->statusEvent($actor,$node,(string)$p->consignment_id,(string)$p->parcel_id,(string)$p->current_status,(string)$m->manifest_status,(string)$m->manifest_id);$toCustody=match((string)$m->manifest_status){'PD','PU','NPU'=>'PICKUP_DRIVER','OS'=>'LINEHAUL_DRIVER','OD','NOK'=>'DELIVERY_DRIVER','OK'=>'RECIPIENT',default=>'NODE'};$toCustodian=match($toCustody){'PICKUP_DRIVER','LINEHAUL_DRIVER','DELIVERY_DRIVER'=>$m->assigned_driver_id,'NODE'=>$node,default=>null};$toNode=$toCustody==='NODE'?$node:null;DB::table('parcel_custody_events')->insert(['custody_event_id'=>(string)Str::uuid(),'hq_id'=>$actor->hqId,'event_sequence'=>$this->nextSequence('parcel_custody_events',(string)$p->consignment_id),'consignment_id'=>$p->consignment_id,'parcel_id'=>$p->parcel_id,'from_node_id'=>$p->current_node_id,'to_node_id'=>$toNode,'from_custody_type'=>$p->current_custody_type,'to_custody_type'=>$toCustody,'from_custodian_id'=>$p->current_custodian_id,'to_custodian_id'=>$toCustodian,'command_name'=>'MANIFEST_'.(string)$m->manifest_status,'initiator_id'=>$actor->userId,'manifest_id'=>$m->manifest_id,'route_plan_id'=>$e['route_plan_id'],'route_plan_leg_id'=>$e['route_plan_leg_id'],'created_at'=>now()]);}
    private function pickupAssigned(AuthenticatedPrincipal $actor,string $node,object $p,object $m):void{$task=DB::table('pickup_tasks')->where(['hq_id'=>$actor->hqId,'consignment_id'=>$p->consignment_id])->lockForUpdate()->first();if($task===null){DB::table('pickup_tasks')->insert(['pickup_task_id'=>(string)Str::uuid(),'hq_id'=>$actor->hqId,'consignment_id'=>$p->consignment_id,'node_id'=>$node,'assigned_driver_id'=>$m->assigned_driver_id,'status'=>'ASSIGNED','version'=>1,'assigned_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);}else DB::table('pickup_tasks')->where('pickup_task_id',$task->pickup_task_id)->update(['assigned_driver_id'=>$m->assigned_driver_id,'status'=>'ASSIGNED','version'=>(int)$task->version+1,'assigned_at'=>now(),'updated_at'=>now()]);DB::table('consignments')->where('consignment_id',$p->consignment_id)->update(['pickup_man_id'=>$m->assigned_driver_id]);DB::table('drivers')->where(['driver_id'=>$m->assigned_driver_id,'availability_status'=>'AVAILABLE'])->update(['availability_status'=>'ON_MISSION','updated_at'=>now()]);}
    private function pickupCompleted(object $p):void{DB::table('pickup_tasks')->where('consignment_id',$p->consignment_id)->whereIn('status',['ASSIGNED','IN_PROGRESS'])->update(['status'=>'COMPLETED','completed_at'=>now(),'version'=>DB::raw('version + 1'),'updated_at'=>now()]);}
    private function pickupFailed(object $p):void{DB::table('pickup_tasks')->where('consignment_id',$p->consignment_id)->update(['status'=>'FAILED','failed_at'=>now(),'version'=>DB::raw('version + 1'),'updated_at'=>now()]);}
    private function pickupReceived(object $p):void{DB::table('drivers')->where(['driver_id'=>$p->current_custodian_id,'availability_status'=>'ON_MISSION'])->update(['availability_status'=>'AVAILABLE','updated_at'=>now()]);}
    private function deliveryCompleted(AuthenticatedPrincipal $actor,object $p):void{$task=DB::table('delivery_tasks')->where(['hq_id'=>$actor->hqId,'consignment_id'=>$p->consignment_id])->lockForUpdate()->first();if($task){DB::table('delivery_tasks')->where('delivery_task_id',$task->delivery_task_id)->update(['status'=>'COMPLETED','delivered_at'=>now(),'version'=>(int)$task->version+1,'updated_at'=>now()]);DB::table('drivers')->where(['driver_id'=>$task->assigned_driver_id,'availability_status'=>'ON_MISSION'])->update(['availability_status'=>'AVAILABLE','updated_at'=>now()]);}}
    private function deliveryFailed(AuthenticatedPrincipal $actor,object $p):void{$task=DB::table('delivery_tasks')->where(['hq_id'=>$actor->hqId,'consignment_id'=>$p->consignment_id])->lockForUpdate()->first();if($task){DB::table('delivery_tasks')->where('delivery_task_id',$task->delivery_task_id)->update(['status'=>'FAILED','version'=>(int)$task->version+1,'updated_at'=>now()]);DB::table('drivers')->where(['driver_id'=>$task->assigned_driver_id,'availability_status'=>'ON_MISSION'])->update(['availability_status'=>'AVAILABLE','updated_at'=>now()]);}}

    private function statusEvent(AuthenticatedPrincipal $actor,string $node,string $consignment,?string $parcel,string $old,string $new,string $manifest):void{DB::table('consignment_status_events')->insert(['status_event_id'=>(string)Str::uuid(),'hq_id'=>$actor->hqId,'event_sequence'=>$this->nextSequence('consignment_status_events',$consignment),'consignment_id'=>$consignment,'parcel_id'=>$parcel,'previous_status'=>$old,'new_status'=>$new,'initiator_id'=>$actor->userId,'node_id'=>$node,'manifest_id'=>$manifest,'reason_code'=>'MANIFEST_CONFIRMED','created_at'=>now()]);}
    private function nextSequence(string $table,string $consignment):int{return((int)DB::table($table)->where('consignment_id',$consignment)->max('event_sequence'))+1;}
    private function lockedManifest(AuthenticatedPrincipal $actor,string $node,string $id):object{$m=DB::table('manifests')->where(['hq_id'=>$actor->hqId,'node_id'=>$node,'manifest_id'=>$id])->lockForUpdate()->first();if($m===null)throw new ApiException(ApiErrorCode::ResourceNotFound,404,'Resource not found.');return$m;}
    private function manifestVersion(object $m,int $expected):void{if((int)$m->version!==$expected)throw new ApiException(ApiErrorCode::ManifestVersionConflict,409,'The Manifest version is stale.',details:['current_version'=>(int)$m->version]);}
    private function lockedPendingCase(AuthenticatedPrincipal $actor,string $manifest):object{$c=DB::table('operational_exception_cases')->where(['hq_id'=>$actor->hqId,'manifest_id'=>$manifest])->orderByDesc('submission_sequence')->lockForUpdate()->first();if($c===null)throw new ApiException(ApiErrorCode::ExceptionReviewRequired,422,'A pending Exception is required.');if((string)$c->case_status!=='PENDING')throw new ApiException(ApiErrorCode::ExceptionAlreadyDecided,422,'The Exception is already decided.');return$c;}
    private function exceptionVersion(object $case,int $expected):void{if((int)$case->version!==$expected)throw new ApiException(ApiErrorCode::ExceptionVersionConflict,409,'The Exception version is stale.',details:['current_version'=>(int)$case->version]);}
    private function differentReviewer(AuthenticatedPrincipal $actor,object $case):void{if((string)$case->submitted_by===$actor->userId)throw new ApiException(ApiErrorCode::ExceptionReviewerConflict,422,'The submitter cannot review the same Exception.');}
    private function exceptionHistory(AuthenticatedPrincipal $actor,object $case,string $action,?string $reason,int $manifestVersion,int $exceptionVersion):void{DB::table('operational_exception_history')->insert(['exception_history_id'=>(string)Str::uuid(),'hq_id'=>$actor->hqId,'exception_case_id'=>$case->exception_case_id,'action'=>$action,'actor_id'=>$actor->userId,'safe_note'=>$reason,'manifest_version'=>$manifestVersion,'exception_version'=>$exceptionVersion,'created_at'=>now()]);}
    /** @return array<string,mixed> */
    private function caseResource(object $c):array{$history=DB::table('operational_exception_history as h')->join('users as u','u.user_id','=','h.actor_id')->where('h.exception_case_id',$c->exception_case_id)->orderBy('h.created_at')->get()->map(fn(object $h):array=>['action'=>(string)$h->action,'actor'=>['user_id'=>(string)$h->actor_id,'display_name'=>(string)$h->display_name],'safe_reason'=>$h->safe_note,'manifest_version'=>(int)($h->manifest_version??1),'exception_version'=>(int)($h->exception_version??1),'occurred_at'=>(string)$h->created_at])->all();$submitter=DB::table('users')->where('user_id',$c->submitted_by)->value('display_name');$reviewer=$c->reviewed_by?DB::table('users')->where('user_id',$c->reviewed_by)->value('display_name'):null;return['exception_case_id'=>(string)$c->exception_case_id,'manifest_id'=>(string)$c->manifest_id,'exception_type'=>(string)$c->exception_type,'status'=>(string)$c->case_status,'version'=>(int)$c->version,'submission_sequence'=>(int)$c->submission_sequence,'reason_code'=>(string)$c->reason_code,'description'=>(string)$c->description,'submitted_by'=>['user_id'=>(string)$c->submitted_by,'display_name'=>(string)$submitter],'submitted_at'=>(string)$c->created_at,'reviewed_by'=>$c->reviewed_by?['user_id'=>(string)$c->reviewed_by,'display_name'=>(string)$reviewer]:null,'reviewed_at'=>$c->reviewed_at,'decision_reason'=>$c->decision_note,'history'=>$history];}
    private function commandEvent(AuthenticatedPrincipal $actor,string $resource,string $consignment,string $command,string $status,string $correlationId):void{$this->outbox->write($actor->hqId,'MANIFEST',$resource,'operations.command.executed',$correlationId,['command'=>$command,'resource_id'=>$resource,'consignment_id'=>$consignment,'status'=>$status]);}
    private function reviewAccess(AuthenticatedPrincipal $actor,string $node):void{$this->access($actor,$node,'manifest.approve');$this->requirePermission($actor,'live_operations.intervene');}
    private function requirePermission(AuthenticatedPrincipal $actor,string $permission):void{$c=$this->authorization->resolve($actor);if(!in_array($permission,$c['permissions'],true))throw new ApiException(ApiErrorCode::PermissionDenied,403,'Access denied.');}
    private function access(AuthenticatedPrincipal $actor,string $node,string $permission):void{if($actor->hqId===null)throw new ApiException(ApiErrorCode::TenantAccessDenied,403,'Access denied.');$c=$this->authorization->resolve($actor);if(!collect($c['module_entitlements'])->contains(fn($e)=>$e['module_code']==='Manifest'&&$e['status']==='ENABLED'))throw new ApiException(ApiErrorCode::EntitlementDisabled,403,'Access denied.');if(!in_array($permission,$c['permissions'],true))throw new ApiException(ApiErrorCode::PermissionDenied,403,'Access denied.');if(!in_array($node,$c['accessible_node_ids'],true))throw new ApiException(ApiErrorCode::ScopeAccessDenied,403,'Access denied.');}
}
