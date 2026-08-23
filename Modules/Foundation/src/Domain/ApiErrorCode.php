<?php

declare(strict_types=1);

namespace Modules\Foundation\Domain;

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
    case ConsignmentNotEditable = 'CONSIGNMENT_NOT_EDITABLE';
    case ManifestNotEditable = 'MANIFEST_NOT_EDITABLE';
    case ManifestNoSuccessfulParcels = 'MANIFEST_NO_SUCCESSFUL_PARCELS';
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
