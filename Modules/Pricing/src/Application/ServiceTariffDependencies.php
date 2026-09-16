<?php
declare(strict_types=1);
namespace Modules\Pricing\Application;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final class ServiceTariffDependencies
{
    public function ids(string $versionId): array
    {
        return DB::table('tariff_service_attachments')->where('tariff_version_id',$versionId)->pluck('service_tariff_family_id')->all();
    }

    public function replace(string $versionId, array $ids): void
    {
        DB::table('tariff_service_attachments')->where('tariff_version_id',$versionId)->delete();
        foreach ($ids as $id) DB::table('tariff_service_attachments')->insert(['tariff_version_id'=>$versionId,'service_tariff_family_id'=>$id]);
    }

    public function resolve(array $ids, string $hqId, ?string $parentZoneVersionId, CarbonImmutable $asOf, array $localRules): array
    {
        if (count(array_unique($ids)) !== count($ids)) $this->reject('تعرفهٔ خدمات تکراری است.');
        $group = $parentZoneVersionId ? DB::table('pricing_zone_set_versions')->where('zone_set_version_id',$parentZoneVersionId)->value('pricing_zone_set_id') : null;
        $charges = [];
        foreach ($localRules as $rule) {
            $code = DB::table('pricing_charge_types')->where('charge_type_id',$rule['charge_type_id'])->value('code');
            $charges[$this->chargeKey((string)$code)] = true;
        }
        $result = [];
        foreach ($ids as $id) {
            $family = DB::table('tariff_families')->where('tariff_family_id',$id)->where('tariff_kind','SERVICE')->where(fn($q)=>$q->whereNull('hq_id')->orWhere('hq_id',$hqId))->first();
            if (! $family) $this->reject('تعرفهٔ خدمات در دسترس نیست.');
            $version = DB::table('tariff_versions')->where('tariff_family_id',$id)->where('status','PUBLISHED')->where('valid_from','<=',$asOf->utc()->format('Y-m-d H:i:s.u'))->where(fn($q)=>$q->whereNull('valid_to')->orWhere('valid_to','>',$asOf->utc()->format('Y-m-d H:i:s.u')))->orderByDesc('version_number')->first();
            if (! $version) $this->reject('تعرفهٔ خدمات نسخهٔ منتشرشده و معتبر ندارد.');
            $serviceGroup = $version->zone_set_version_id ? DB::table('pricing_zone_set_versions')->where('zone_set_version_id',$version->zone_set_version_id)->value('pricing_zone_set_id') : null;
            if ($serviceGroup !== null && $serviceGroup !== $group) $this->reject('گروه زون تعرفهٔ خدمات باید با تعرفهٔ حمل یکسان باشد.');
            $code = (string) DB::table('pricing_charge_types')->where('charge_type_id',$family->service_charge_type_id)->value('code');
            $key = $this->chargeKey($code);
            if (isset($charges[$key])) $this->reject('این هزینه هم در قواعد و هم در تعرفهٔ خدمات تعریف شده یا دوبار متصل شده است.');
            $charges[$key] = true;
            $result[] = (object)[...(array)$version,'tariff_kind'=>'SERVICE','service_charge_type_id'=>$family->service_charge_type_id,'charge_code'=>$code,'title'=>$family->title];
        }
        return $result;
    }

    public function chargeKey(string $code): string { return $code === 'INSURANCE_FEE' ? 'INSURANCE' : $code; }

    public function assertCompatibleSuccessor(array $version): void
    {
        if ($version['tariff_kind'] !== 'SERVICE' || ! $version['zone_set_version_id']) return;
        $group=DB::table('pricing_zone_set_versions')->where('zone_set_version_id',$version['zone_set_version_id'])->value('pricing_zone_set_id');
        $parents=DB::table('tariff_service_attachments as a')->join('tariff_versions as v','v.tariff_version_id','=','a.tariff_version_id')->join('pricing_zone_set_versions as z','z.zone_set_version_id','=','v.zone_set_version_id')
            ->where('a.service_tariff_family_id',$version['tariff_family_id'])->whereIn('v.status',['APPROVED','PUBLISHED'])->where('z.pricing_zone_set_id','!=',$group)
            ->where(fn($q)=>$q->whereNull('v.valid_to')->orWhere('v.valid_to','>',CarbonImmutable::now()));
        if ($parents->exists()) $this->reject('این خانواده به تعرفهٔ حمل با گروه زون دیگری متصل است؛ نسخهٔ جدید نمی‌تواند گروه ناسازگار داشته باشد.');
    }
    private function reject(string $message): never { throw new ApiException(ApiErrorCode::ValidationError,422,$message,details:['reason_code'=>'PRICING_SERVICE_DEPENDENCY_INVALID']); }
}
