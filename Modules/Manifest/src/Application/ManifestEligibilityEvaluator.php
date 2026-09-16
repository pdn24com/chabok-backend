<?php

declare(strict_types=1);

namespace Modules\Manifest\Application;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Manifest\Domain\ManifestEligibilityReason as Reason;
use Modules\Manifest\Domain\ManifestPolicy;

final readonly class ManifestEligibilityEvaluator
{
    public function __construct(private ManifestPolicy $policy) {}

    /** @return array<string,mixed> */
    public function evaluate(object $parcel, object $manifest, string $nodeId): array
    {
        $consignment=DB::table('consignments')->where(['hq_id'=>$manifest->hq_id,'consignment_id'=>$parcel->consignment_id])->first();
        if($consignment===null)return Reason::metadata(Reason::ParcelNotFound);
        if($consignment->service_offering_id!==null&&$consignment->commercial_pricing_state!=='LOCKED')return Reason::metadata(Reason::PricingStale);
        if(!$this->policy->canTransition((string)$parcel->current_status,(string)$manifest->manifest_status))return Reason::metadata(Reason::StatusNotAllowed);
        return match((string)$manifest->manifest_status){
            'PD'=>$this->nodeCustody($parcel,$consignment->pickup_node_id,$nodeId),
            'PU','NPU'=>$this->pickupAssignment($parcel,$manifest,$nodeId),
            'IR'=>(string)$parcel->current_status==='OS'?$this->movementReception($parcel,$manifest,$nodeId,'IR'):$this->pickupReception($parcel,$manifest,$nodeId),
            'ROU'=>$this->nodeCustody($parcel,$nodeId,$nodeId),
            'OF'=>$this->outbound($parcel,$manifest,$nodeId),
            'OS'=>$this->departure($parcel,$manifest,$nodeId),
            'CI'=>$this->movementReception($parcel,$manifest,$nodeId,'CI'),
            'OD'=>$this->deliveryAssignment($parcel,$manifest,$consignment,$nodeId),
            'OK','NOK'=>$this->deliveryCompletion($parcel,$manifest,$nodeId),
            default=>Reason::metadata(Reason::StatusNotAllowed),
        };
    }

    public function applyCandidateScope(Builder $query,object $manifest,string $nodeId):void
    {
        $query->whereIn('p.current_status',$this->policy->sourceStatuses((string)$manifest->manifest_status));
        if(in_array((string)$manifest->manifest_status,['IR','CI'],true)&&(string)$manifest->operational_context_type!=='PICKUP_RECEPTION'){
            $query->where('p.current_custody_type','LINEHAUL_DRIVER')->whereExists(fn(Builder $q)=>$q->selectRaw('1')->from('manifest_parcels as source_mp')->whereColumn('source_mp.parcel_id','p.parcel_id')->where(['source_mp.manifest_id'=>$manifest->source_manifest_id,'source_mp.manifest_parcel_status'=>'SUCCEEDED']));
            return;
        }
        if((string)$manifest->manifest_status==='OS'){
            $query->whereExists(fn(Builder $q)=>$q->selectRaw('1')->from('manifest_parcels as source_mp')->whereColumn('source_mp.parcel_id','p.parcel_id')->where(['source_mp.manifest_id'=>$manifest->source_manifest_id,'source_mp.manifest_parcel_status'=>'SUCCEEDED']));
        }
        if(in_array((string)$manifest->manifest_status,['PU','NPU'],true)){$query->where('p.current_custody_type','PICKUP_DRIVER');return;}
        if(in_array((string)$manifest->manifest_status,['OK','NOK'],true)){$query->where('p.current_custody_type','DELIVERY_DRIVER');return;}
        $query->where(fn(Builder $q)=>$q->where('p.current_node_id',$nodeId)->orWhere('c.pickup_node_id',$nodeId)->orWhere('c.delivery_node_id',$nodeId));
    }

    /** @return array<string,mixed> */
    private function nodeCustody(object $p,mixed $requiredNode,string $node):array
    {
        if((string)$requiredNode!==$node||(string)$p->current_node_id!==$node)return Reason::metadata(Reason::CurrentNodeMismatch);
        if((string)$p->current_custody_type!=='NODE'||(string)$p->current_custodian_id!==$node)return Reason::metadata(Reason::CustodyMismatch);
        return Reason::metadata(Reason::Eligible);
    }

    /** @return array<string,mixed> */
    private function pickupAssignment(object $p,object $m,string $node):array
    {
        if((string)$p->current_custody_type!=='PICKUP_DRIVER'||(string)$p->current_custodian_id!==(string)$m->assigned_driver_id)return Reason::metadata(Reason::PickupAssignmentMismatch);
        $ok=DB::table('pickup_tasks')->where(['hq_id'=>$m->hq_id,'consignment_id'=>$p->consignment_id,'node_id'=>$node,'assigned_driver_id'=>$m->assigned_driver_id])->whereIn('status',['ASSIGNED','IN_PROGRESS','COMPLETED'])->exists();
        return Reason::metadata($ok?Reason::Eligible:Reason::PickupAssignmentMismatch);
    }

    /** @return array<string,mixed> */
    private function pickupReception(object $p,object $m,string $node):array
    {
        if((string)$p->current_custody_type!=='PICKUP_DRIVER'||$p->current_custodian_id===null)return Reason::metadata(Reason::CustodyMismatch);
        $ok=DB::table('pickup_tasks')->where(['hq_id'=>$m->hq_id,'consignment_id'=>$p->consignment_id,'node_id'=>$node,'assigned_driver_id'=>$p->current_custodian_id,'status'=>'COMPLETED'])->exists();
        return Reason::metadata($ok?Reason::Eligible:Reason::PickupTaskNotCompleted);
    }

    /** @return array<string,mixed> */
    private function movementReception(object $p,object $m,string $node,string $target):array
    {
        if((string)$p->current_custody_type!=='LINEHAUL_DRIVER'||(string)$p->current_custodian_id!==(string)$m->assigned_driver_id)return Reason::metadata(Reason::CustodyMismatch);
        $e=DB::table('manifest_parcels')->where(['hq_id'=>$m->hq_id,'manifest_id'=>$m->source_manifest_id,'parcel_id'=>$p->parcel_id,'manifest_parcel_status'=>'SUCCEEDED'])->first();
        if($e===null||(string)$e->destination_node_id!==$node||(string)$e->assigned_driver_id!==(string)$m->assigned_driver_id||(string)$e->assigned_vehicle_id!==(string)$m->assigned_vehicle_id)return Reason::metadata(Reason::PreviousMovementMismatch);
        $leg=DB::table('route_plan_legs')->where(['hq_id'=>$m->hq_id,'route_plan_leg_id'=>$e->route_plan_leg_id,'route_plan_id'=>$e->route_plan_id,'destination_node_id'=>$node,'status'=>'IN_TRANSIT'])->first();
        if($leg===null)return Reason::metadata(Reason::RouteLegNotReady);
        $hasNext=DB::table('route_plan_legs')->where(['hq_id'=>$m->hq_id,'route_plan_id'=>$leg->route_plan_id,'leg_order'=>(int)$leg->leg_order+1,'origin_node_id'=>$node])->exists();
        if(($target==='CI'&&!$hasNext)||($target==='IR'&&$hasNext))return Reason::metadata(Reason::RouteLegNotReady);
        return Reason::metadata(Reason::Eligible);
    }

    /** @return array<string,mixed> */
    private function outbound(object $p,object $m,string $node):array
    {
        $base=$this->nodeCustody($p,$node,$node);if(!$base['eligible'])return$base;
        if((string)$p->active_route_plan_id!==(string)$m->route_plan_id)return Reason::metadata(Reason::RoutePlanMismatch);
        if((string)$p->active_route_plan_leg_id!==(string)$m->route_plan_leg_id)return Reason::metadata(Reason::RouteLegMismatch);
        return Reason::metadata(DB::table('route_plan_legs')->where(['hq_id'=>$m->hq_id,'route_plan_leg_id'=>$m->route_plan_leg_id,'origin_node_id'=>$node])->whereIn('status',['PENDING','ROUTED'])->exists()?Reason::Eligible:Reason::RouteLegNotReady);
    }

    /** @return array<string,mixed> */
    private function departure(object $p,object $m,string $node):array
    {
        $base=$this->nodeCustody($p,$node,$node);if(!$base['eligible'])return$base;
        $source=DB::table('manifest_parcels')->where(['hq_id'=>$m->hq_id,'manifest_id'=>$m->source_manifest_id,'parcel_id'=>$p->parcel_id,'manifest_parcel_status'=>'SUCCEEDED'])->first();
        if($source===null||(string)$source->destination_node_id!==(string)$m->destination_node_id||(string)$source->route_definition_version_leg_id!==(string)$m->route_definition_version_leg_id)return Reason::metadata(Reason::PreviousMovementMismatch);
        $leg=DB::table('route_plan_legs')->where(['hq_id'=>$m->hq_id,'route_plan_leg_id'=>$p->active_route_plan_leg_id,'route_plan_id'=>$p->active_route_plan_id,'origin_node_id'=>$node,'destination_node_id'=>$m->destination_node_id,'source_route_definition_version_leg_id'=>$m->route_definition_version_leg_id,'status'=>'OUTBOUND_CONFIRMED'])->first();
        return Reason::metadata($leg?Reason::Eligible:Reason::RouteLegNotReady);
    }

    /** @return array<string,mixed> */
    private function deliveryAssignment(object $p,object $m,object $c,string $node):array
    {
        $base=$this->nodeCustody($p,$node,$node);if(!$base['eligible'])return$base;
        if((string)$c->delivery_node_id!==$node)return Reason::metadata(Reason::DeliveryNodeMismatch);
        if($p->active_route_plan_id!==null&&DB::table('route_plan_legs')->where(['hq_id'=>$m->hq_id,'route_plan_id'=>$p->active_route_plan_id])->whereNot('status','RECEIVED')->exists())return Reason::metadata(Reason::DeliveryRouteIncomplete);
        return Reason::metadata(Reason::Eligible);
    }

    /** @return array<string,mixed> */
    private function deliveryCompletion(object $p,object $m,string $node):array
    {
        if((string)$p->current_custody_type!=='DELIVERY_DRIVER'||(string)$p->current_custodian_id!==(string)$m->assigned_driver_id)return Reason::metadata(Reason::CustodyMismatch);
        $ok=DB::table('delivery_tasks')->where(['hq_id'=>$m->hq_id,'consignment_id'=>$p->consignment_id,'node_id'=>$node,'assigned_driver_id'=>$m->assigned_driver_id,'status'=>'IN_PROGRESS'])->exists();
        return Reason::metadata($ok?Reason::Eligible:Reason::DriverUnavailable);
    }
}
