<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\Enums;

enum CatalogValidationCode: string
{
    case CommitmentEffectiveIntervalInvalid = 'COMMITMENT_EFFECTIVE_INTERVAL_INVALID';
    case CommitmentEffectiveIntervalOverlap = 'COMMITMENT_EFFECTIVE_INTERVAL_OVERLAP';
    case CommitmentScheduleVersionNotPublished = 'COMMITMENT_SCHEDULE_VERSION_NOT_PUBLISHED';
    case CommitmentScopeRequired = 'COMMITMENT_SCOPE_REQUIRED';
    case CommitmentWeekdayRequired = 'COMMITMENT_WEEKDAY_REQUIRED';
    case CommitmentWindowIntervalInvalid = 'COMMITMENT_WINDOW_INTERVAL_INVALID';
    case CommitmentWindowRequired = 'COMMITMENT_WINDOW_REQUIRED';
    case ComputedDeliveryAnchorUnavailable = 'COMPUTED_DELIVERY_ANCHOR_UNAVAILABLE';
    case ComputedDeliveryConfigurationRequired = 'COMPUTED_DELIVERY_CONFIGURATION_REQUIRED';
    case PickupWindowRequired = 'PICKUP_WINDOW_REQUIRED';
    case ServiceAvailabilityRequired = 'SERVICE_AVAILABILITY_REQUIRED';
    case ServiceDependencyNotPublished = 'SERVICE_DEPENDENCY_NOT_PUBLISHED';
    case ServiceEffectiveIntervalInvalid = 'SERVICE_EFFECTIVE_INTERVAL_INVALID';
    case ServiceEffectiveIntervalOverlap = 'SERVICE_EFFECTIVE_INTERVAL_OVERLAP';
    case ServiceLabelRequired = 'SERVICE_LABEL_REQUIRED';
    case ServiceOptionConditionRequired = 'SERVICE_OPTION_CONDITION_REQUIRED';
    case ServiceOptionVersionNotPublished = 'SERVICE_OPTION_VERSION_NOT_PUBLISHED';
}
