<?php
declare(strict_types=1);
namespace Modules\Consignment\Infrastructure\Http;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Consignment\Application\OperationalStatusCatalog;
use Modules\Foundation\Application\ApiResponder;
use Modules\Foundation\Application\StrictPayload;

final readonly class OperationalStatusController {
    public function __construct(private OperationalStatusCatalog $catalog) {}
    public function index(Request $r): JsonResponse { return ApiResponder::success($r,$this->catalog->entries($r->attributes->get('principal'))); }
    public function store(Request $r): JsonResponse { return $this->save($r,null); }
    public function update(Request $r,string $id): JsonResponse { return $this->save($r,$id); }
    private function save(Request $r,?string $id): JsonResponse {
        $rules=['title_fa'=>'required|string|max:200','title_en'=>'nullable|string|max:200','partial_title_fa'=>'nullable|string|max:200','partial_title_en'=>'nullable|string|max:200','tone'=>'required|in:neutral,info,brand,warning,danger,success,ink','status_group'=>'nullable|in:NEW_ROUTED,IN_OPERATION,EXCEPTION,COMPLETED,CANCELLED','is_terminal'=>'required|boolean','is_active'=>'required|boolean','sort_order'=>'required|integer|min:0|max:10000'];
        $rules += $id ? ['expected_version'=>'required|integer|min:1'] : ['code'=>'required|string|regex:/^[A-Z][A-Z0-9_]{1,31}$/','scope'=>'required|in:GLOBAL,TENANT'];
        StrictPayload::assertOnly($r,array_keys($rules));
        return ApiResponder::success($r,$this->catalog->save($r->attributes->get('principal'),$id,$r->validate($rules),(string)$r->attributes->get('correlation_id')),status:$id?200:201);
    }
}
