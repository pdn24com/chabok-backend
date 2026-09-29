<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Mappers;

use Modules\ServiceCatalog\Application\Dto\ResolvedServiceOfferingDto;
use Modules\ServiceCatalog\Application\Dto\ServiceEligibilitySelectionDto;

/** Projects the catalog's native resolution into its inter-module contract. */
final class ServiceEligibilityInput
{
    public static function selection(ResolvedServiceOfferingDto $selection): ServiceEligibilitySelectionDto
    {
        $version = $selection->version;
        $dependencies = $selection->dependencies;

        return new ServiceEligibilitySelectionDto(
            $version->service_offering_id, $version->service_offering_version_id,
            $version->serviceTypeVersion->service_type_id, $dependencies->type->service_type_version_id,
            $version->shippingMethodVersion->shipping_method_id, $dependencies->method->shipping_method_version_id,
            $selection->decision->outcome->value, $selection->decision->reasonCodes, $version->labels ?? [],
            $dependencies->type->labels ?? [], $dependencies->method->labels ?? [], $selection->options, $selection->commitment,
        );
    }
}
