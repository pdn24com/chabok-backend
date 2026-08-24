<?php

declare(strict_types=1);

namespace Modules\Manifest\Application;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ManifestOperationalContext
{
    /** @return array<string,mixed> */
    public function options(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        $node = $this->node((string) $actor->hqId, $nodeId);
        $contexts = collect($this->operationalOptions($actor, $nodeId, $node))
            ->map(function (array $option): array {
                unset($option['resolution']);

                return $option;
            })->all();

        return [
            'current_node' => $this->nodeResource($node),
            'transition_contracts' => $this->transitionContracts(),
            'contexts' => $contexts,
            'drivers' => $this->drivers((string) $actor->hqId, $nodeId),
            'vehicles' => $this->vehicles((string) $actor->hqId, $nodeId),
        ];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function normalize(AuthenticatedPrincipal $actor, string $nodeId, array $input): array
    {
        $target = (string) ($input['manifest_status'] ?? '');
        $contextKey = (string) ($input['context_key'] ?? '');
        $node = $this->node((string) $actor->hqId, $nodeId);
        $option = collect($this->operationalOptions($actor, $nodeId, $node))->first(
            fn (array $candidate): bool => $candidate['manifest_status'] === $target && hash_equals((string) $candidate['context_key'], $contextKey),
        );
        if (! is_array($option)) throw new ApiException(ApiErrorCode::UnsupportedManifestTransition, 422, 'The selected server context is unavailable.');
        $normalized = $option['resolution'];
        $normalized['manifest_status'] = $target;
        $normalized['context_key'] = $contextKey;
        $normalized['manifest_type'] = $this->manifestType($target);
        $normalized['operational_context_type'] = $option['operational_context_type'];

        $requiredCapability = match ($target) { 'PD' => 'PICKUP', 'OD' => 'DELIVERY', 'OS' => 'LINEHAUL', default => null };
        if ($requiredCapability !== null) {
            $driverId = isset($input['assigned_driver_id']) ? (string) $input['assigned_driver_id'] : '';
            $this->assertDriver((string) $actor->hqId, $nodeId, $driverId, $requiredCapability);
            $normalized['assigned_driver_id'] = $driverId;
        }
        if ($target === 'OS') {
            $vehicleId = isset($input['assigned_vehicle_id']) ? (string) $input['assigned_vehicle_id'] : '';
            $this->assertVehicle((string) $actor->hqId, $nodeId, $vehicleId);
            $normalized['assigned_vehicle_id'] = $vehicleId;
        }
        if (! in_array($target, ['PD','OD','OS'], true) && array_key_exists('assigned_driver_id', $input)
            && $input['assigned_driver_id'] !== null && (string) $input['assigned_driver_id'] !== (string) ($normalized['assigned_driver_id'] ?? '')) {
            throw new ApiException(ApiErrorCode::DriverOutOfScope, 422, 'The Driver is derived from the selected server context.');
        }
        return $normalized;
    }

    /** @return list<array<string,mixed>> */
    private function operationalOptions(AuthenticatedPrincipal $actor, string $nodeId, object $node): array
    {
        $options = [
            $this->baseOption('PD', 'PICKUP_ASSIGNMENT', "PD:{$nodeId}", $node, 'Pickup assignment'),
            $this->baseOption('IR', 'PICKUP_RECEPTION', "IR:PICKUP:{$nodeId}", $node, 'Pickup reception'),
            $this->baseOption('ROU', 'ROUTE_REGISTRATION', "ROU:{$nodeId}", $node, 'Route registration'),
            $this->baseOption('OD', 'DELIVERY_ASSIGNMENT', "OD:{$nodeId}", $node, 'Delivery assignment'),
            ...$this->pickupOptions((string) $actor->hqId, $nodeId),
            ...$this->routeLegOptions((string) $actor->hqId, $nodeId),
            ...$this->movementReceptionOptions((string) $actor->hqId, $nodeId),
            ...$this->deliveryOptions((string) $actor->hqId, $nodeId),
        ];

        return collect($options)->map(function (array $option) use ($actor, $nodeId): array {
            $target = (string) $option['manifest_status'];
            $relatedNodeId = in_array($target, ['IR', 'CI'], true)
                ? $option['resolution']['origin_node_id']
                : (in_array($target, ['OF', 'OS'], true)
                    ? $option['resolution']['destination_node_id']
                    : ($option['resolution']['destination_node_id'] ?? $option['resolution']['origin_node_id'] ?? $nodeId));
            $option['related_node'] = $this->nullableNode((string) $actor->hqId, $relatedNodeId);
            $option['related_node_role'] = in_array($target, ['IR', 'CI'], true)
                ? 'SOURCE'
                : (in_array($target, ['OF', 'OS'], true) ? 'DESTINATION' : 'COUNTERPARTY');

            return $option;
        })->all();
    }

    /** @return array<string,mixed> */
    public function summary(object $manifest): array
    {
        $hq = (string) $manifest->hq_id;
        $target = (string) $manifest->manifest_status;
        $relatedNodeId = in_array($target, ['IR', 'CI'], true)
            ? $manifest->origin_node_id
            : (in_array($target, ['OF', 'OS'], true)
                ? $manifest->destination_node_id
                : ($manifest->destination_node_id ?? $manifest->origin_node_id));

        return [
            'operational_context_type' => (string) $manifest->operational_context_type,
            'current_node' => $this->nullableNode($hq, $manifest->node_id),
            'related_node' => $this->nullableNode($hq, $relatedNodeId),
            'related_node_role' => in_array($target, ['IR', 'CI'], true)
                ? 'SOURCE'
                : (in_array($target, ['OF', 'OS'], true) ? 'DESTINATION' : 'COUNTERPARTY'),
            'origin_node' => $this->nullableNode($hq, $manifest->origin_node_id),
            'destination_node' => $this->nullableNode($hq, $manifest->destination_node_id),
            'route_plan' => $this->routePlan($hq, $manifest->route_plan_id),
            'route_leg' => $this->routeLeg($hq, $manifest->route_plan_leg_id),
            'driver' => $this->driver($hq, $manifest->assigned_driver_id),
            'vehicle' => $this->vehicle($hq, $manifest->assigned_vehicle_id),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function pickupOptions(string $hq, string $node): array
    {
        return DB::table('pickup_tasks as t')->join('consignments as c','c.consignment_id','=','t.consignment_id')->where(['t.hq_id'=>$hq,'t.node_id'=>$node])->whereIn('t.status',['ASSIGNED','IN_PROGRESS','COMPLETED'])->orderBy('c.consignment_number')->get(['t.*','c.consignment_number'])->flatMap(function(object $r) use($node):array {
            $s=$this->emptySelection(); $s['origin_node_id']=$node; $s['destination_node_id']=$node; $s['assigned_driver_id']=(string)$r->assigned_driver_id;
            return [$this->option('PU','PICKUP_COMPLETION','PICKUP:'.$r->pickup_task_id.':PU',"{$r->consignment_number} · pickup completion",$s),$this->option('NPU','PICKUP_EXCEPTION','PICKUP:'.$r->pickup_task_id.':NPU',"{$r->consignment_number} · pickup exception",$s)];
        })->all();
    }

    /** @return list<array<string,mixed>> */
    private function routeLegOptions(string $hq, string $node): array
    {
        $rows=DB::table('route_plan_legs as l')->join('route_plans as p','p.route_plan_id','=','l.route_plan_id')->join('consignments as c','c.consignment_id','=','p.consignment_id')->where(['l.hq_id'=>$hq,'l.origin_node_id'=>$node])->whereIn('l.status',['PENDING','ROUTED'])->whereExists(fn(Builder $q)=>$q->selectRaw('1')->from('parcels as parcel')->whereColumn('parcel.active_route_plan_leg_id','l.route_plan_leg_id')->whereColumn('parcel.active_route_plan_id','l.route_plan_id')->whereIn('parcel.current_status',['ROU','CI']))->orderBy('c.consignment_number')->get(['l.*','p.route_definition_version_id','c.consignment_number']); $out=[];
        foreach($rows as $r){$s=$this->emptySelection();$s['origin_node_id']=$node;$s['destination_node_id']=(string)$r->destination_node_id;$s['route_plan_id']=(string)$r->route_plan_id;$s['route_plan_leg_id']=(string)$r->route_plan_leg_id;$s['route_definition_version_id']=(string)$r->route_definition_version_id;$s['route_definition_version_leg_id']=(string)$r->source_route_definition_version_leg_id;$out[]=$this->option('OF','OUTBOUND_CONFIRMATION','OF:'.$r->route_plan_leg_id,"{$r->consignment_number} · outbound leg {$r->leg_order}",$s);}
        $outboundManifests=DB::table('manifests as m')->where(['m.hq_id'=>$hq,'m.node_id'=>$node,'m.manifest_status'=>'OF','m.state'=>'CLOSED'])->whereExists(fn(Builder $q)=>$q->selectRaw('1')->from('manifest_parcels as mp')->join('parcels as parcel','parcel.parcel_id','=','mp.parcel_id')->whereColumn('mp.manifest_id','m.manifest_id')->where(['mp.manifest_parcel_status'=>'SUCCEEDED','parcel.current_status'=>'OF']))->orderBy('m.closed_at')->get();
        foreach($outboundManifests as $r){$s=$this->emptySelection();$s['origin_node_id']=(string)$r->origin_node_id;$s['destination_node_id']=(string)$r->destination_node_id;$s['route_definition_version_leg_id']=(string)$r->route_definition_version_leg_id;$s['source_manifest_id']=(string)$r->manifest_id;$out[]=$this->option('OS','LINEHAUL_DEPARTURE','OS:OF:'.$r->manifest_id,'Linehaul departure · '.$r->manifest_number,$s);}
        return $out;
    }

    /** @return list<array<string,mixed>> */
    private function movementReceptionOptions(string $hq,string $node):array
    {
        $out=[];$manifests=DB::table('manifests')->where(['hq_id'=>$hq,'manifest_status'=>'OS','state'=>'CLOSED','destination_node_id'=>$node])->whereExists(fn(Builder $q)=>$q->selectRaw('1')->from('manifest_parcels as mp')->join('parcels as p','p.parcel_id','=','mp.parcel_id')->whereColumn('mp.manifest_id','manifests.manifest_id')->where(['mp.manifest_parcel_status'=>'SUCCEEDED','p.current_status'=>'OS']))->orderBy('closed_at')->get();
        foreach($manifests as $r){$s=$this->emptySelection();$s['origin_node_id']=(string)$r->origin_node_id;$s['destination_node_id']=(string)$r->destination_node_id;$s['source_manifest_id']=(string)$r->manifest_id;$s['assigned_driver_id']=(string)$r->assigned_driver_id;$s['assigned_vehicle_id']=(string)$r->assigned_vehicle_id;$hasTransit=DB::table('manifest_parcels as mp')->join('route_plan_legs as current','current.route_plan_leg_id','=','mp.route_plan_leg_id')->where(['mp.manifest_id'=>$r->manifest_id,'mp.manifest_parcel_status'=>'SUCCEEDED'])->whereExists(fn(Builder $q)=>$q->selectRaw('1')->from('route_plan_legs as next')->whereColumn('next.route_plan_id','current.route_plan_id')->whereRaw('next.leg_order = current.leg_order + 1')->where('next.origin_node_id',$node))->exists();$hasFinal=DB::table('manifest_parcels as mp')->join('route_plan_legs as current','current.route_plan_leg_id','=','mp.route_plan_leg_id')->where(['mp.manifest_id'=>$r->manifest_id,'mp.manifest_parcel_status'=>'SUCCEEDED'])->whereNotExists(fn(Builder $q)=>$q->selectRaw('1')->from('route_plan_legs as next')->whereColumn('next.route_plan_id','current.route_plan_id')->whereRaw('next.leg_order = current.leg_order + 1'))->exists();if($hasTransit)$out[]=$this->option('CI','TRANSIT_UNLOAD','CI:OS:'.$r->manifest_id,'Transit unload · '.$r->manifest_number,$s);if($hasFinal)$out[]=$this->option('IR','MOVEMENT_RECEPTION','IR:OS:'.$r->manifest_id,'Final reception · '.$r->manifest_number,$s);}
        return$out;
    }

    /** @return list<array<string,mixed>> */
    private function deliveryOptions(string $hq,string $node):array
    {
        return DB::table('delivery_tasks as t')->join('consignments as c','c.consignment_id','=','t.consignment_id')->where(['t.hq_id'=>$hq,'t.node_id'=>$node,'t.status'=>'IN_PROGRESS'])->orderBy('c.consignment_number')->get(['t.*','c.consignment_number'])->flatMap(function(object $r)use($node):array{$s=$this->emptySelection();$s['origin_node_id']=$node;$s['assigned_driver_id']=(string)$r->assigned_driver_id;return[$this->option('OK','DELIVERY_COMPLETION','DELIVERY:'.$r->delivery_task_id.':OK',"{$r->consignment_number} · delivery completion",$s),$this->option('NOK','DELIVERY_EXCEPTION','DELIVERY:'.$r->delivery_task_id.':NOK',"{$r->consignment_number} · delivery exception",$s)];})->all();
    }

    /** @return array<string,mixed> */
    private function baseOption(string $target,string $type,string $key,object $node,string $label):array{$s=$this->emptySelection();$s['origin_node_id']=(string)$node->node_id;if($type==='PICKUP_RECEPTION')$s['destination_node_id']=(string)$node->node_id;return $this->option($target,$type,$key,$label.' · '.$node->node_title,$s);}
    /** @param array<string,mixed> $selection @return array<string,mixed> */
    private function option(string $target,string $type,string $key,string $label,array $selection):array
    {
        $request = [
            'expected_version' => 0,
            'manifest_status' => $target,
            'context_key' => $key,
        ];
        if ($selection['assigned_driver_id'] !== null) {
            $request['assigned_driver_id'] = $selection['assigned_driver_id'];
        }
        if ($selection['assigned_vehicle_id'] !== null) {
            $request['assigned_vehicle_id'] = $selection['assigned_vehicle_id'];
        }

        return [
            'context_key' => $key,
            'operational_context_type' => $type,
            'manifest_status' => $target,
            'label' => ['fa' => $label, 'en' => $label],
            'selection' => $request,
            'resolution' => $selection,
        ];
    }
    /** @return array<string,mixed> */
    private function emptySelection():array{return['origin_node_id'=>null,'destination_node_id'=>null,'route_plan_id'=>null,'route_definition_version_id'=>null,'route_plan_leg_id'=>null,'route_definition_version_leg_id'=>null,'source_manifest_id'=>null,'assigned_driver_id'=>null,'assigned_vehicle_id'=>null];}

    private function assertDriver(string $hq,string $node,string $id,string $capability):void{$r=DB::table('drivers')->where(['hq_id'=>$hq,'driver_id'=>$id])->first();if($r===null||(string)$r->home_node_id!==$node)throw new ApiException(ApiErrorCode::DriverOutOfScope,422,'The selected Driver is outside the authorized Node.');if(!DB::table('driver_capabilities')->where(['hq_id'=>$hq,'driver_id'=>$id,'capability'=>$capability])->exists())throw new ApiException(ApiErrorCode::DriverIncapable,422,'The selected Driver lacks the required capability.');$allowed=$capability==='PICKUP'?['AVAILABLE','ON_MISSION']:['AVAILABLE'];if((string)$r->status!=='ACTIVE'||!in_array((string)$r->availability_status,$allowed,true))throw new ApiException(ApiErrorCode::DriverUnavailable,422,'The selected Driver is unavailable.');}
    private function assertVehicle(string $hq,string $node,string $id):void{$r=DB::table('vehicles')->where(['hq_id'=>$hq,'vehicle_id'=>$id])->first();if($r===null||(string)$r->home_node_id!==$node)throw new ApiException(ApiErrorCode::VehicleOutOfScope,422,'The selected Vehicle is outside the authorized Node.');if((string)$r->status!=='ACTIVE'||(string)$r->availability_status!=='AVAILABLE')throw new ApiException(ApiErrorCode::VehicleUnavailable,422,'The selected Vehicle is unavailable.');}
    /** @return list<array<string,mixed>> */
    private function drivers(string $hq,string $node):array{return DB::table('drivers as d')->where(['d.hq_id'=>$hq,'d.home_node_id'=>$node,'d.status'=>'ACTIVE'])->where(fn(Builder $q)=>$q->where('d.availability_status','AVAILABLE')->orWhere(fn(Builder $pickup)=>$pickup->where('d.availability_status','ON_MISSION')->whereExists(fn(Builder $capability)=>$capability->selectRaw('1')->from('driver_capabilities as dc')->whereColumn('dc.driver_id','d.driver_id')->where(['dc.hq_id'=>$hq,'dc.capability'=>'PICKUP']))))->orderBy('d.driver_code')->get(['d.*'])->map(fn(object $r):array=>[...$this->driverResource($r),'capabilities'=>DB::table('driver_capabilities')->where(['hq_id'=>$hq,'driver_id'=>$r->driver_id])->orderBy('capability')->pluck('capability')->all()])->all();}
    /** @return list<array<string,mixed>> */
    private function vehicles(string $hq,string $node):array{return DB::table('vehicles')->where(['hq_id'=>$hq,'home_node_id'=>$node,'status'=>'ACTIVE','availability_status'=>'AVAILABLE'])->orderBy('vehicle_code')->get()->map(fn(object $r):array=>[...$this->vehicleResource($r),'capabilities'=>['LINEHAUL','DELIVERY']])->all();}
    private function node(string $hq,string $node):object{$r=DB::table('nodes')->where(['hq_id'=>$hq,'node_id'=>$node,'status'=>'ACTIVE'])->first();if($r===null)throw new ApiException(ApiErrorCode::CurrentNodeMismatch,422,'The operational Node is unavailable.');return$r;}
    private function nullableNode(string $hq,mixed $id):?array{return$id===null?null:(($r=DB::table('nodes')->where(['hq_id'=>$hq,'node_id'=>$id])->first())?$this->nodeResource($r):null);}
    private function routePlan(string $hq,mixed $id):?array{if($id===null)return null;$r=DB::table('route_plans as p')->join('consignments as c','c.consignment_id','=','p.consignment_id')->join('route_definitions as d','d.route_definition_id','=','p.route_definition_id')->where(['p.hq_id'=>$hq,'p.route_plan_id'=>$id])->first();return$r?['route_plan_id'=>(string)$r->route_plan_id,'consignment_id'=>(string)$r->consignment_id,'consignment_number'=>(string)$r->consignment_number,'route_definition_id'=>(string)$r->route_definition_id,'route_code'=>(string)$r->route_code,'route_title'=>(string)$r->route_title,'status'=>(string)$r->status,'version'=>(int)$r->version]:null;}
    private function routeLeg(string $hq,mixed $id):?array{if($id===null)return null;$r=DB::table('route_plan_legs')->where(['hq_id'=>$hq,'route_plan_leg_id'=>$id])->first();return$r?['route_plan_leg_id'=>(string)$r->route_plan_leg_id,'leg_order'=>(int)$r->leg_order,'status'=>(string)$r->status,'origin_node_id'=>(string)$r->origin_node_id,'destination_node_id'=>(string)$r->destination_node_id]:null;}
    private function driver(string $hq,mixed $id):?array{if($id===null)return null;$r=DB::table('drivers')->where(['hq_id'=>$hq,'driver_id'=>$id])->first();return$r?$this->driverResource($r):null;}
    private function vehicle(string $hq,mixed $id):?array{if($id===null)return null;$r=DB::table('vehicles')->where(['hq_id'=>$hq,'vehicle_id'=>$id])->first();return$r?$this->vehicleResource($r):null;}
    private function nodeResource(object $r):array{return['node_id'=>(string)$r->node_id,'node_code'=>(string)$r->node_code,'node_title'=>(string)$r->node_title,'node_type'=>(string)$r->node_type,'status'=>(string)$r->status];}
    private function driverResource(object $r):array{return['driver_id'=>(string)$r->driver_id,'driver_code'=>(string)$r->driver_code,'display_name'=>(string)$r->display_name,'home_node_id'=>(string)$r->home_node_id,'status'=>(string)$r->status,'availability_status'=>(string)$r->availability_status];}
    private function vehicleResource(object $r):array{return['vehicle_id'=>(string)$r->vehicle_id,'vehicle_code'=>(string)$r->vehicle_code,'registration_number'=>(string)$r->registration_number,'vehicle_type'=>(string)$r->vehicle_type,'home_node_id'=>(string)$r->home_node_id,'status'=>(string)$r->status,'availability_status'=>(string)$r->availability_status];}
    private function manifestType(string $t):string{return match($t){'PD'=>'PICKUP_ASSIGNMENT','PU'=>'PICKUP_COMPLETION','NPU'=>'PICKUP_EXCEPTION','IR'=>'INBOUND_RECEPTION','ROU'=>'ROUTE_REGISTRATION','OF'=>'OUTBOUND_TRANSFER','OS'=>'LINEHAUL_DEPARTURE','CI'=>'TRANSIT_UNLOAD','OD'=>'DELIVERY_ASSIGNMENT','OK'=>'DELIVERY_COMPLETION','NOK'=>'DELIVERY_EXCEPTION'};}

    /** @return list<array<string,mixed>> */
    private function transitionContracts():array{$sources=['PD'=>['CFM'],'PU'=>['PD'],'NPU'=>['PD'],'IR'=>['PU','OS'],'ROU'=>['IR'],'OF'=>['ROU','CI'],'OS'=>['OF'],'CI'=>['OS'],'OD'=>['IR'],'OK'=>['OD'],'NOK'=>['OD']];$types=['PD'=>['PICKUP_ASSIGNMENT'],'PU'=>['PICKUP_COMPLETION'],'NPU'=>['PICKUP_EXCEPTION'],'IR'=>['PICKUP_RECEPTION','MOVEMENT_RECEPTION'],'ROU'=>['ROUTE_REGISTRATION'],'OF'=>['OUTBOUND_CONFIRMATION'],'OS'=>['LINEHAUL_DEPARTURE'],'CI'=>['TRANSIT_UNLOAD'],'OD'=>['DELIVERY_ASSIGNMENT'],'OK'=>['DELIVERY_COMPLETION'],'NOK'=>['DELIVERY_EXCEPTION']];return collect(array_keys($sources))->map(fn(string $t):array=>['target_status'=>$t,'allowed_source_statuses'=>$sources[$t],'operational_context_types'=>$types[$t],'current_node_requirement'=>'SERVER_VALIDATED','origin_node_derivation'=>'SERVER_CONTEXT','destination_node_derivation'=>'SERVER_CONTEXT','route_plan_requirement'=>in_array($t,['ROU','OF','OS','CI','OD'],true)?'REQUIRED_OR_RESOLVED':'SOURCE_DEPENDENT','route_leg_requirement'=>in_array($t,['ROU','OF','OS','CI'],true)?'REQUIRED':'SOURCE_DEPENDENT','required_driver_capability'=>match($t){'PD'=>'PICKUP','OS'=>'LINEHAUL','OD'=>'DELIVERY',default=>null},'driver_requirement'=>in_array($t,['PD','OS','OD'],true)?'REQUIRED':'DERIVED','vehicle_requirement'=>$t==='OS'?'REQUIRED_ACTIVE_AVAILABLE':'FORBIDDEN','expected_custody_types'=>match($t){'PU','NPU'=>['PICKUP_DRIVER'],'IR'=>['PICKUP_DRIVER','LINEHAUL_DRIVER'],'CI'=>['LINEHAUL_DRIVER'],'OK','NOK'=>['DELIVERY_DRIVER'],default=>['NODE']},'exception_review_required'=>in_array($t,['NPU','NOK'],true),'immutable_evidence'=>['AUTHENTICATED_ACTOR','SERVER_TIMESTAMP','MANIFEST','MANIFEST_PARCELS','STATUS_HISTORY','CUSTODY_HISTORY','AUDIT','OUTBOX']])->all();}
}
