<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\Enums;

/** Why a configured commitment window cannot produce an instance for a requested service date. */
enum CommitmentWindowUnavailability
{
    /** Key into lang/<locale>/api.php. */
    public function messageKey(): string
    {
        return match ($this) {
            self::WindowInvalid => 'servicecatalog.commitment_window_is_invalid_for_offering',
            self::DateInvalid => 'servicecatalog.commitment_window_is_unavailable_on_date',
            self::CutoffPassed => 'servicecatalog.pickup_booking_cutoff_has_passed',
        };
    }

    public function reasonCode(CommitmentWindowType $windowType): string
    {
        return match ($this) {
            self::WindowInvalid => $windowType->value.'_WINDOW_INVALID',
            self::DateInvalid => $windowType->value.'_WINDOW_DATE_INVALID',
            self::CutoffPassed => CommitmentWindowType::Pickup->value.'_CUTOFF_PASSED',
        };
    }
    case WindowInvalid;
    case DateInvalid;
    case CutoffPassed;
}
