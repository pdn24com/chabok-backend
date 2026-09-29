<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Serialization;

use Modules\ServiceCatalog\Application\Dto\OfferingDependencyVersionsDto;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOptionVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceTypeVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ShippingMethodVersionRecord;

final class CatalogDocument
{
    public static function version(ServiceTypeVersionRecord|ShippingMethodVersionRecord|ServiceOfferingVersionRecord|ServiceOptionVersionRecord $version, bool $withChildren = true, bool $withIdentityStatus = false): array
    {
        $identity = match (true) {
            $version instanceof ServiceTypeVersionRecord => $version->type,
            $version instanceof ShippingMethodVersionRecord => $version->method,
            $version instanceof ServiceOfferingVersionRecord => $version->offering,
            $version instanceof ServiceOptionVersionRecord => $version->option,
        };
        $result = [...$version->attributesToArray(), 'code' => $identity->code];
        if ($withIdentityStatus) {
            $result['identity_status'] = $identity->status;
        }
        if ($withChildren && $version instanceof ServiceOfferingVersionRecord) {
            $result['option_rules'] = $version->optionRules->map(fn ($rule) => $rule->attributesToArray())->all();
            $result['eligibility_rules'] = $version->eligibilityRules->map(fn ($rule) => $rule->attributesToArray())->all();
            $result['coverage_references'] = $version->coverageReferences->map(fn ($reference) => $reference->attributesToArray())->all();
            $result['availability_bindings'] = $version->availabilityBindings->map(fn ($binding) => $binding->attributesToArray())->all();
            $result['commitment_binding'] = $version->commitmentBinding?->attributesToArray();
        }

        return $result;
    }

    public static function offering(ServiceOfferingVersionRecord $version, OfferingDependencyVersionsDto $dependencies): array
    {
        return [
            ...$version->attributesToArray(),
            'offering_code' => $version->offering->code,
            'service_type_id' => $version->serviceTypeVersion->service_type_id,
            'shipping_method_id' => $version->shippingMethodVersion->shipping_method_id,
            'service_type_version_id' => $dependencies->type->service_type_version_id,
            'shipping_method_version_id' => $dependencies->method->shipping_method_version_id,
            'service_type_labels' => $dependencies->type->labels,
            'shipping_method_labels' => $dependencies->method->labels,
        ];
    }
}
