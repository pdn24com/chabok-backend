<?php

declare(strict_types=1);

namespace Modules\Manifest\Domain\Enums;

enum ManifestEligibilityReason: string
{
    public function eligible(): bool
    {
        return $this === self::Eligible;
    }
    case Eligible = 'ELIGIBLE';

    case ParcelNotFound = 'PARCEL_NOT_FOUND';

    case ParcelAlreadyAssigned = 'PARCEL_ALREADY_ASSIGNED';

    case PricingStale = 'PRICING_STALE';

    case StatusNotAllowed = 'STATUS_NOT_ALLOWED';

    case CurrentNodeMismatch = 'CURRENT_NODE_MISMATCH';

    case CustodyMismatch = 'CUSTODY_MISMATCH';

    case PickupTaskNotCompleted = 'PICKUP_TASK_NOT_COMPLETED';

    case RoutePlanMismatch = 'ROUTE_PLAN_MISMATCH';

    case RouteLegMismatch = 'ROUTE_LEG_MISMATCH';

    case RouteLegNotReady = 'ROUTE_LEG_NOT_READY';

    case ConfigVersionUnavailable = 'CONFIG_VERSION_UNAVAILABLE';

    case PickupAssignmentMismatch = 'PICKUP_ASSIGNMENT_MISMATCH';

    case RoutePlanUnavailable = 'ROUTE_PLAN_UNAVAILABLE';

    case RouteLegUnavailable = 'ROUTE_LEG_UNAVAILABLE';

    case PreviousMovementMismatch = 'PREVIOUS_MOVEMENT_MISMATCH';

    case DeliveryRouteIncomplete = 'DELIVERY_ROUTE_INCOMPLETE';

    case DeliveryNodeMismatch = 'DELIVERY_NODE_MISMATCH';

    case DriverUnavailable = 'DRIVER_UNAVAILABLE';

    case DriverIncapable = 'DRIVER_INCAPABLE';

    case DriverOutOfScope = 'DRIVER_OUT_OF_SCOPE';

    case VehicleUnavailable = 'VEHICLE_UNAVAILABLE';

    case VehicleIncapable = 'VEHICLE_INCAPABLE';

    case VehicleOutOfScope = 'VEHICLE_OUT_OF_SCOPE';

    case CoverageNotFound = 'COVERAGE_NOT_FOUND';

    case CoverageAmbiguous = 'COVERAGE_AMBIGUOUS';

    case ExceptionReviewRequired = 'EXCEPTION_REVIEW_REQUIRED';
}
