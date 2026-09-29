<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\Enums;

enum CatalogResource: string
{
    public function identityKey(): string
    {
        return match ($this) {
            self::ServiceType => 'service_type_id',
            self::ShippingMethod => 'shipping_method_id',
            self::Offering => 'service_offering_id',
            self::Option => 'service_option_id',
            self::CommitmentSchedule => 'commitment_schedule_id',
        };
    }

    public function versionKey(): string
    {
        return match ($this) {
            self::ServiceType => 'service_type_version_id',
            self::ShippingMethod => 'shipping_method_version_id',
            self::Offering => 'service_offering_version_id',
            self::Option => 'service_option_version_id',
            self::CommitmentSchedule => 'commitment_schedule_version_id',
        };
    }
    case ServiceType = 'service-types';
    case ShippingMethod = 'shipping-methods';
    case Offering = 'offerings';
    case Option = 'options';
    case CommitmentSchedule = 'commitment-schedules';
}
