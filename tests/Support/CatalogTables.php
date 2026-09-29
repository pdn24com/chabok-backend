<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Eloquent\Builder;
use Modules\ServiceCatalog\Domain\Enums\CatalogResource;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOptionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOptionVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceTypeRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceTypeVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ShippingMethodRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ShippingMethodVersionRecord;

/** Direct table access for catalogue boundary tests that seed and mutate rows the repository never writes. */
final class CatalogTables
{
    public static function identities(CatalogResource $resource): Builder
    {
        return match ($resource) {
            CatalogResource::ServiceType => ServiceTypeRecord::query(),
            CatalogResource::ShippingMethod => ShippingMethodRecord::query(),
            CatalogResource::Offering => ServiceOfferingRecord::query(),
            CatalogResource::Option => ServiceOptionRecord::query(),
            CatalogResource::CommitmentSchedule => CommitmentScheduleRecord::query(),
        };
    }

    public static function versions(CatalogResource $resource): Builder
    {
        return match ($resource) {
            CatalogResource::ServiceType => ServiceTypeVersionRecord::query(),
            CatalogResource::ShippingMethod => ShippingMethodVersionRecord::query(),
            CatalogResource::Offering => ServiceOfferingVersionRecord::query(),
            CatalogResource::Option => ServiceOptionVersionRecord::query(),
            CatalogResource::CommitmentSchedule => CommitmentScheduleVersionRecord::query(),
        };
    }
}
