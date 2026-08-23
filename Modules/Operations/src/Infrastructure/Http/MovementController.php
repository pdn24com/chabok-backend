<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Application\ApiResponder;
use Modules\Foundation\Application\StrictPayload;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Operations\Application\MovementService;

final readonly class MovementController
{
    public function __construct(private MovementService $movement) {}
    public function plans(Request $r): JsonResponse { return ApiResponder::success($r,$this->movement->listRoutePlans($r->attributes->get('principal'),$this->node($r))); }
    public function plan(Request $r,string $consignmentId): JsonResponse { StrictPayload::assertOnly($r,[]);return ApiResponder::success($r,$this->movement->plan($r->attributes->get('principal'),$this->node($r),$consignmentId,$this->correlation($r)),status:201); }
    public function showPlan(Request $r,string $id): JsonResponse { return ApiResponder::success($r,$this->movement->routePlan($r->attributes->get('principal'),$this->node($r),$id)); }
    public function cluster(Request $r,string $consignmentId): JsonResponse { StrictPayload::assertOnly($r,['expected_route_plan_version']);$in=$r->validate(['expected_route_plan_version'=>['required','integer','min:1']]);return ApiResponder::success($r,$this->movement->cluster($r->attributes->get('principal'),$this->node($r),$consignmentId,(int)$in['expected_route_plan_version'],$this->correlation($r))); }
    public function createRun(Request $r): JsonResponse { StrictPayload::assertOnly($r,['route_plan_leg_id','driver_id','vehicle_id']);$in=$r->validate(['route_plan_leg_id'=>['required','uuid'],'driver_id'=>['required','uuid'],'vehicle_id'=>['required','uuid']]);return ApiResponder::success($r,$this->movement->createRun($r->attributes->get('principal'),$this->node($r),(string)$in['route_plan_leg_id'],(string)$in['driver_id'],(string)$in['vehicle_id'],$this->correlation($r)),status:201); }
    public function showRun(Request $r,string $id): JsonResponse { return ApiResponder::success($r,$this->movement->run($r->attributes->get('principal'),$this->node($r),$id)); }
    public function load(Request $r,string $id): JsonResponse { StrictPayload::assertOnly($r,['expected_version','parcel_ids']);$in=$r->validate(['expected_version'=>['required','integer','min:1'],'parcel_ids'=>['required','array','min:1'],'parcel_ids.*'=>['required','uuid','distinct']]);return ApiResponder::success($r,$this->movement->load($r->attributes->get('principal'),$this->node($r),$id,$in['parcel_ids'],(int)$in['expected_version'],$this->correlation($r))); }
    public function depart(Request $r,string $id): JsonResponse { return ApiResponder::success($r,$this->movement->depart($r->attributes->get('principal'),$this->node($r),$id,$this->expected($r),$this->correlation($r))); }
    public function arrive(Request $r,string $id): JsonResponse { return ApiResponder::success($r,$this->movement->arrive($r->attributes->get('principal'),$this->node($r),$id,$this->expected($r),$this->correlation($r))); }
    public function close(Request $r,string $id): JsonResponse { return ApiResponder::success($r,$this->movement->close($r->attributes->get('principal'),$this->node($r),$id,$this->expected($r),$this->correlation($r))); }
    private function expected(Request $r): int { StrictPayload::assertOnly($r,['expected_version']);return(int)$r->validate(['expected_version'=>['required','integer','min:1']])['expected_version']; }
    private function correlation(Request $r): string { return(string)$r->attributes->get('correlation_id'); }
    private function node(Request $r): string { $id=$r->attributes->get('node_id');if(!is_string($id)||$id==='')throw new ApiException(ApiErrorCode::ValidationError,422,'An active operational node is required.');return$id; }
}
