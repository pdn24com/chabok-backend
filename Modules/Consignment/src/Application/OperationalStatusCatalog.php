<?php
declare(strict_types=1);
namespace Modules\Consignment\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class OperationalStatusCatalog
{
    public function __construct(private AuthorizationContextResolver $authorization, private AuditWriter $audit) {}

    public function visible(?string $hqId): \Illuminate\Database\Query\Builder
    {
        return DB::table('operational_statuses')->where(fn ($q) => $q->whereNull('hq_id')->when($hqId !== null, fn ($q) => $q->orWhere('hq_id',$hqId)));
    }

    public function entries(AuthenticatedPrincipal $actor): array
    {
        $context=$this->access($actor,false);
        return $this->visible($actor->hqId)->orderBy('sort_order')->orderBy('code')->get()->map(fn ($row) => $this->present((array)$row,$context,$actor))->all();
    }

    public function codes(?string $hqId, bool $manifestOnly=false): array
    {
        return $this->visible($hqId)->when($manifestOnly,fn ($q)=>$q->where('manifest_enabled',true))->pluck('code')->all();
    }

    public function save(AuthenticatedPrincipal $actor, ?string $id, array $input, string $correlation): array
    {
        $context=$this->access($actor,true);
        return DB::transaction(function () use ($actor,$id,$input,$correlation,$context) {
            DB::table('operational_status_catalog_lock')->where('id',1)->lockForUpdate()->first();
            $before=$id ? DB::table('operational_statuses')->where('status_id',$id)->lockForUpdate()->first() : null;
            if ($id && !$before) throw new ApiException(ApiErrorCode::ResourceNotFound,404,'Resource not found.');
            $global=$before ? $before->hq_id===null : ($input['scope']??'TENANT')==='GLOBAL';
            if (($global && empty($context['is_platform_admin'])) || (!$global && ($actor->hqId===null || ($before && $before->hq_id!==$actor->hqId)))) throw new ApiException(ApiErrorCode::PermissionDenied,403,'Access denied.');
            if ($before && (int)$before->version !== (int)$input['expected_version']) throw new ApiException(ApiErrorCode::VersionConflict,409,'Status changed; reload and retry.');
            $code=$before?->code ?? $input['code'];
            if (!$before && DB::table('operational_statuses')->where('code',$code)->where(fn ($q)=>$global ? $q : $q->whereNull('hq_id')->orWhere('hq_id',$actor->hqId))->exists()) throw new ApiException(ApiErrorCode::ValidationError,409,'This status code already exists.');
            if ($code==='D01') throw new ApiException(ApiErrorCode::ValidationError,422,'D01 is reserved for the legacy adapter.');
            if ($before?->is_system && (!$input['is_active'] || (bool)$input['is_terminal'] !== (bool)$before->is_terminal || ($input['status_group']??null)!==$before->status_group)) throw new ApiException(ApiErrorCode::ValidationError,422,'Built-in workflow semantics cannot be changed through status presentation settings.');
            $fields=array_intersect_key($input,array_flip(['title_fa','title_en','partial_title_fa','partial_title_en','tone','status_group','is_terminal','is_active','sort_order']));
            if ($before && collect($fields)->every(fn ($value,$key)=>$value==$before->$key)) return $this->present((array)$before,$context,$actor);
            $row=[...($before ? (array)$before : ['status_id'=>(string)Str::uuid(),'hq_id'=>$global?null:$actor->hqId,'owner_key'=>$global?'GLOBAL':$actor->hqId,'code'=>$code,'is_system'=>false,'manifest_enabled'=>false,'created_at'=>now()]),...$fields,'version'=>$before ? $before->version+1 : 1,'updated_at'=>now()];
            if ($before) DB::table('operational_statuses')->where('status_id',$id)->update($row); else DB::table('operational_statuses')->insert($row);
            DB::table('operational_status_revisions')->insert(['revision_id'=>(string)Str::uuid(),'status_id'=>$row['status_id'],'version'=>$row['version'],'actor_id'=>$actor->userId,'snapshot'=>json_encode($row,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'created_at'=>now()]);
            $this->audit->write($actor->hqId,$actor->userId,'OPERATIONAL_STATUS_SAVED','OPERATIONAL_STATUS',$row['status_id'],$correlation,before:$before?(array)$before:null,after:$row,sourceClient:'BRANCH_PANEL');
            return $this->present($row,$context,$actor);
        });
    }

    private function present(array $row,array $context,AuthenticatedPrincipal $actor): array
    {
        foreach (['is_system','is_active','is_terminal','manifest_enabled'] as $key) $row[$key]=(bool)$row[$key];
        $row['can_manage']=$row['hq_id']===null ? !empty($context['is_platform_admin']) : $row['hq_id']===$actor->hqId && $this->tenantManager($context);
        return $row;
    }

    private function tenantManager(array $context): bool
    {
        return in_array('operational_status.manage',$context['permissions']??[],true) && collect($context['scopes']??[])->contains(fn ($s)=>($s['scope_type']??null)==='TENANT');
    }

    private function access(AuthenticatedPrincipal $actor,bool $write): array
    {
        $c=$this->authorization->resolve($actor);
        if (!empty($c['is_platform_admin'])) return $c;
        if (!$actor->hqId) throw new ApiException(ApiErrorCode::TenantAccessDenied,403,'Access denied.');
        $consignment=collect($c['module_entitlements']??[])->contains(fn ($e)=>$e['module_code']==='Consignment' && $e['status']==='ENABLED');
        $manifest=collect($c['module_entitlements']??[])->contains(fn ($e)=>$e['module_code']==='Manifest' && $e['status']==='ENABLED');
        $read=($consignment && (in_array('consignment.view',$c['permissions']??[],true)||$this->tenantManager($c))) || ($manifest && in_array('manifest.view',$c['permissions']??[],true));
        if (!$read || ($write && (!$consignment || !$this->tenantManager($c)))) throw new ApiException(ApiErrorCode::PermissionDenied,403,'Access denied.');
        return $c;
    }
}
