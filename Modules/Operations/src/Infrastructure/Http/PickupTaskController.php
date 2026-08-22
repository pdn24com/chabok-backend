<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Application\ApiResponder;
use Modules\Foundation\Application\StrictPayload;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Operations\Application\PickupTaskService;

final readonly class PickupTaskController
{
    public function __construct(private PickupTaskService $tasks) {}
    public function index(Request $request): JsonResponse { return ApiResponder::success($request, $this->tasks->list($request->attributes->get('principal'), $this->node($request))); }
    public function store(Request $request): JsonResponse { StrictPayload::assertOnly($request, ['consignment_id']); $in=$request->validate(['consignment_id'=>['required','uuid']]); return ApiResponder::success($request,$this->tasks->create($request->attributes->get('principal'),$this->node($request),(string)$in['consignment_id'],$this->correlation($request)),status:201); }
    public function show(Request $request,string $id): JsonResponse { return ApiResponder::success($request,$this->tasks->get($request->attributes->get('principal'),$this->node($request),$id)); }
    public function assign(Request $request,string $id): JsonResponse { StrictPayload::assertOnly($request,['expected_version','driver_id']);$in=$request->validate(['expected_version'=>['required','integer','min:1'],'driver_id'=>['required','uuid']]);return ApiResponder::success($request,$this->tasks->assign($request->attributes->get('principal'),$this->node($request),$id,(string)$in['driver_id'],(int)$in['expected_version'],$this->correlation($request))); }
    public function complete(Request $request,string $id): JsonResponse { return ApiResponder::success($request,$this->tasks->complete($request->attributes->get('principal'),$this->node($request),$id,$this->expected($request),$this->correlation($request))); }
    public function fail(Request $request,string $id): JsonResponse { StrictPayload::assertOnly($request,['expected_version','reason_code','reason']);$in=$request->validate(['expected_version'=>['required','integer','min:1'],'reason_code'=>['required','string','max:80'],'reason'=>['required','string','max:500']]);return ApiResponder::success($request,$this->tasks->fail($request->attributes->get('principal'),$this->node($request),$id,(int)$in['expected_version'],(string)$in['reason_code'],(string)$in['reason'],$this->correlation($request))); }
    private function expected(Request $r): int { StrictPayload::assertOnly($r,['expected_version']);return (int)$r->validate(['expected_version'=>['required','integer','min:1']])['expected_version']; }
    private function correlation(Request $r): string { return (string)$r->attributes->get('correlation_id'); }
    private function node(Request $r): string { $id=$r->attributes->get('node_id');if(!is_string($id)||$id==='')throw new ApiException(ApiErrorCode::ValidationError,422,'An active operational node is required.');return $id; }
}
