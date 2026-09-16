<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final class CatalogResource
{
    /** Identity and revision keys exposed in the existing catalog contract. */

    public static function keys(string $resource): array
    {
        return match ($resource) {
            'service-types' => ['service_type_id', 'service_type_version_id'],
            'shipping-methods' => ['shipping_method_id', 'shipping_method_version_id'],
            'offerings' => ['service_offering_id', 'service_offering_version_id'],
            'options' => ['service_option_id', 'service_option_version_id'],
            'commitment-schedules' => ['commitment_schedule_id', 'commitment_schedule_version_id'],
            default => throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.'),
        };
    }
}
