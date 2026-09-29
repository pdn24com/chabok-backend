<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Enums;

enum ServiceDependencyFailure
{
    /** Key into lang/<locale>/api.php. */
    public function messageKey(): string
    {
        return match ($this) {
            self::DuplicateFamily => 'pricing.service_tariff_is_duplicate',
            self::FamilyUnavailable => 'pricing.service_tariff_is_unavailable',
            self::VersionUnpublished => 'pricing.service_tariff_version_is_unpublished',
            self::ZoneGroupMismatch => 'pricing.service_tariff_zone_set_mismatch',
            self::DuplicateCharge => 'pricing.duplicate_charge',
            self::IncompatibleSuccessor => 'pricing.incompatible_successor',
        };
    }
    case DuplicateFamily;
    case FamilyUnavailable;
    case VersionUnpublished;
    case ZoneGroupMismatch;
    case DuplicateCharge;
    case IncompatibleSuccessor;
}
