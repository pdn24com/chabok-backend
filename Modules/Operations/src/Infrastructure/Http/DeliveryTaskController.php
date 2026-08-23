<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Application\ApiResponder;
use Modules\Foundation\Application\StrictPayload;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Operations\Application\DeliveryTaskService;

final readonly class DeliveryTaskController
{
    public function __construct(private DeliveryTaskService $tasks) {}
    public function index(Request $r): JsonResponse { return ApiResponder::success($r,$this->tasks->list($r->attributes->get('principal'),$this->node($r))); }
    public function show(Request $r,string $id): JsonResponse { return ApiResponder::success($r,$this->tasks->get($r->attributes->get('principal'),$this->node($r),$id)); }
    public function assign(Request $r,string $id): JsonResponse { StrictPayload::assertOnly($r,['expected_version','driver_id']);$in=$r->validate(['expected_version'=>['required','integer','min:1'],'driver_id'=>['required','uuid']]);return ApiResponder::success($r,$this->tasks->assign($r->attributes->get('principal'),$this->node($r),$id,(string)$in['driver_id'],(int)$in['expected_version'],(string)$r->attributes->get('correlation_id'))); }
    public function complete(Request $r,string $id): JsonResponse { StrictPayload::assertOnly($r,['expected_version','recipient_name','delivered_at','proof_type','note']);$in=$r->validate(['expected_version'=>['required','integer','min:1'],'recipient_name'=>['required','string','max:200'],'delivered_at'=>['required','date'],'proof_type'=>['required','in:MANUAL_CONFIRMATION'],'note'=>['sometimes','nullable','string','max:500']]);return ApiResponder::success($r,$this->tasks->complete($r->attributes->get('principal'),$this->node($r),$id,(int)$in['expected_version'],(string)$in['recipient_name'],(string)$in['delivered_at'],$in['note']??null,(string)$r->attributes->get('correlation_id'))); }
    public function fail(Request $r,string $id): JsonResponse { StrictPayload::assertOnly($r,['expected_version','reason_code','reason']);$in=$r->validate(['expected_version'=>['required','integer','min:1'],'reason_code'=>['required','string','max:80'],'reason'=>['required','string','max:500']]);return ApiResponder::success($r,$this->tasks->fail($r->attributes->get('principal'),$this->node($r),$id,(int)$in['expected_version'],(string)$in['reason_code'],(string)$in['reason'],(string)$r->attributes->get('correlation_id'))); }
    private function node(Request $r): string { $id=$r->attributes->get('node_id');if(!is_string($id)||$id==='')throw new ApiException(ApiErrorCode::ValidationError,422,'An active operational node is required.');return $id; }
}
