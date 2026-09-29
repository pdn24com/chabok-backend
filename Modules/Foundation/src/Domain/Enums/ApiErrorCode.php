<?php

declare(strict_types=1);

namespace Modules\Foundation\Domain\Enums;

enum ApiErrorCode: string
{
    case ValidationError = 'VALIDATION_ERROR';
    case AuthenticationRequired = 'AUTHENTICATION_REQUIRED';
    case InvalidCredentials = 'INVALID_CREDENTIALS';
    case Forbidden = 'FORBIDDEN';
    case TenantAccessDenied = 'TENANT_ACCESS_DENIED';
    case EntitlementDisabled = 'ENTITLEMENT_DISABLED';
    case PermissionDenied = 'PERMISSION_DENIED';
    case ScopeAccessDenied = 'SCOPE_ACCESS_DENIED';
    case DelegationDenied = 'DELEGATION_DENIED';
    case OriginNotAllowed = 'ORIGIN_NOT_ALLOWED';
    case PasswordChangeRequired = 'PASSWORD_CHANGE_REQUIRED';
    case ResourceNotFound = 'RESOURCE_NOT_FOUND';
    case MethodNotAllowed = 'METHOD_NOT_ALLOWED';
    case Conflict = 'CONFLICT';
    case VersionConflict = 'VERSION_CONFLICT';
    case IdempotencyKeyReused = 'IDEMPOTENCY_KEY_REUSED';
    case AllocationExceedsReceipt = 'ALLOCATION_EXCEEDS_RECEIPT';
    case ConsignmentNotEditable = 'CONSIGNMENT_NOT_EDITABLE';
    case ConsignmentNumberInvalidFormat = 'CONSIGNMENT_NUMBER_INVALID_FORMAT';
    case ConsignmentNumberInvalidLength = 'CONSIGNMENT_NUMBER_INVALID_LENGTH';
    case ConsignmentNumberPrefixLengthIncompatible = 'CONSIGNMENT_NUMBER_PREFIX_LENGTH_INCOMPATIBLE';
    case ConsignmentNumberInvalidBoundaries = 'CONSIGNMENT_NUMBER_INVALID_BOUNDARIES';
    case ConsignmentNumberRangeOverlap = 'CONSIGNMENT_NUMBER_RANGE_OVERLAP';
    case ConsignmentNumberRangeUnavailable = 'CONSIGNMENT_NUMBER_RANGE_UNAVAILABLE';
    case ConsignmentNumberRangeExhausted = 'CONSIGNMENT_NUMBER_RANGE_EXHAUSTED';
    case ManifestNotEditable = 'MANIFEST_NOT_EDITABLE';
    case ManifestNoSuccessfulParcels = 'MANIFEST_NO_SUCCESSFUL_PARCELS';
    case ManifestVersionConflict = 'MANIFEST_VERSION_CONFLICT';
    case ExceptionVersionConflict = 'EXCEPTION_VERSION_CONFLICT';
    case UnsupportedManifestTransition = 'UNSUPPORTED_MANIFEST_TRANSITION';
    case ManifestSourceStatusMismatch = 'MANIFEST_SOURCE_STATUS_MISMATCH';
    case CurrentNodeMismatch = 'CURRENT_NODE_MISMATCH';
    case CustodyMismatch = 'CUSTODY_MISMATCH';
    case RoutePlanUnavailable = 'ROUTE_PLAN_UNAVAILABLE';
    case RouteLegUnavailable = 'ROUTE_LEG_UNAVAILABLE';
    case RouteLegNotReady = 'ROUTE_LEG_NOT_READY';
    case DriverUnavailable = 'DRIVER_UNAVAILABLE';
    case DriverIncapable = 'DRIVER_INCAPABLE';
    case DriverOutOfScope = 'DRIVER_OUT_OF_SCOPE';
    case VehicleUnavailable = 'VEHICLE_UNAVAILABLE';
    case VehicleIncapable = 'VEHICLE_INCAPABLE';
    case VehicleOutOfScope = 'VEHICLE_OUT_OF_SCOPE';
    case ExceptionReviewRequired = 'EXCEPTION_REVIEW_REQUIRED';
    case ExceptionAlreadyDecided = 'EXCEPTION_ALREADY_DECIDED';
    case ExceptionReviewerConflict = 'EXCEPTION_REVIEWER_CONFLICT';
    case PricingUnavailable = 'PRICING_UNAVAILABLE';
    case PricingRejected = 'PRICING_REJECTED';
    case PricingQuoteExpired = 'PRICING_QUOTE_EXPIRED';
    case PricingQuoteMismatch = 'PRICING_QUOTE_MISMATCH';
    case ServiceIneligible = 'SERVICE_INELIGIBLE';
    case CoverageNotFound = 'COVERAGE_NOT_FOUND';
    case CoverageAmbiguous = 'COVERAGE_AMBIGUOUS';
    case RouteNotFound = 'ROUTE_NOT_FOUND';
    case RouteAmbiguous = 'ROUTE_AMBIGUOUS';
    case ConfigVersionUnavailable = 'CONFIG_VERSION_UNAVAILABLE';
    case PricingTariffNotFound = 'PRICING_TARIFF_NOT_FOUND';
    case PricingZoneUnresolved = 'PRICING_ZONE_UNRESOLVED';
    case PricingZoneAmbiguous = 'PRICING_ZONE_AMBIGUOUS';
    case PricingRuleNotFound = 'PRICING_RULE_NOT_FOUND';
    case PricingRuleAmbiguous = 'PRICING_RULE_AMBIGUOUS';
    case PricingInputChanged = 'PRICING_INPUT_CHANGED';
    case RateLimited = 'RATE_LIMITED';
    case InternalServerError = 'INTERNAL_SERVER_ERROR';
}
