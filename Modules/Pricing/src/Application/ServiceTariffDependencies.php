<?php

declare(strict_types=1);

namespace Modules\Pricing\Application;

use Carbon\CarbonImmutable;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ServiceTariffDependencies
{
    public function __construct(
        private \Modules\Pricing\Application\Repositories\ServiceTariffRepository $repository,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
    )
    {
    }

    public function ids(string $versionId): array
    {
        return $this->repository->attachedFamilyIds($versionId);
    }

    public function replace(string $versionId, array $ids): void
    {
        $this->repository->deleteAttachments($versionId);
        foreach ($ids as $id) {
            $this->repository->attachFamily($versionId, $id);
        }
    }

    public function resolve(array $ids, string $hqId, ?string $parentZoneVersionId, CarbonImmutable $asOf, array $localRules): array
    {
        if (count(array_unique($ids)) !== count($ids)) {
            $this->reject('تعرفهٔ خدمات تکراری است.');
        }
        $group = $parentZoneVersionId ? $this->repository->zoneGroup($parentZoneVersionId) : null;
        $charges = [];
        foreach ($localRules as $rule) {
            $code = $this->repository->chargeCode($rule['charge_type_id']);
            $charges[$this->chargeKey((string) $code)] = true;
        }
        $result = [];
        foreach ($ids as $id) {
            $family = $this->repository->serviceFamily($hqId, $id);
            if (!$family) {
                $this->reject('تعرفهٔ خدمات در دسترس نیست.');
            }
            $version = $this->repository->publishedVersion($id, $asOf);
            if (!$version) {
                $this->reject('تعرفهٔ خدمات نسخهٔ منتشرشده و معتبر ندارد.');
            }
            $serviceGroup = $version->zone_set_version_id ? $this->repository->zoneGroup($version->zone_set_version_id) : null;
            if ($serviceGroup !== null && $serviceGroup !== $group) {
                $this->reject('گروه زون تعرفهٔ خدمات باید با تعرفهٔ حمل یکسان باشد.');
            }
            $code = (string) $this->repository->chargeCode($family->service_charge_type_id);
            $key = $this->chargeKey($code);
            if (isset($charges[$key])) {
                $this->reject('این هزینه هم در قواعد و هم در تعرفهٔ خدمات تعریف شده یا دوبار متصل شده است.');
            }
            $charges[$key] = true;
            $result[] = (object) [
                ...(array) $version,
                'tariff_kind' => 'SERVICE',
                'service_charge_type_id' => $family->service_charge_type_id,
                'charge_code' => $code,
                'title' => $family->title,
            ];
        }
        return $result;
    }

    public function chargeKey(string $code): string
    {
        return $code === 'INSURANCE_FEE' ? 'INSURANCE' : $code;
    }

    public function assertCompatibleSuccessor(array $version): void
    {
        if ($version['tariff_kind'] !== 'SERVICE' || !$version['zone_set_version_id']) {
            return;
        }
        $group = $this->repository->zoneGroup($version['zone_set_version_id']);
        $incompatible = $this->repository->hasIncompatibleParents($version['tariff_family_id'], $group, $this->clock->now());
        if ($incompatible) {
            $this->reject('این خانواده به تعرفهٔ حمل با گروه زون دیگری متصل است؛ نسخهٔ جدید نمی‌تواند گروه ناسازگار داشته باشد.');
        }
    }

    private function reject(string $message): never
    {
        throw new ApiException(ApiErrorCode::ValidationError, 422, $message, details: ['reason_code' => 'PRICING_SERVICE_DEPENDENCY_INVALID']);
    }
}
