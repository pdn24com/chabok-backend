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
    private function correlation(Request $r): string { return(string)$r->attributes->get('correlation_id'); }
    private function node(Request $r): string { $id=$r->attributes->get('node_id');if(!is_string($id)||$id==='')throw new ApiException(ApiErrorCode::ValidationError,422,'An active operational node is required.');return$id; }
}
