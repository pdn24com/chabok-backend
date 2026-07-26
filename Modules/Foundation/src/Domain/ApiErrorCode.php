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
    case IdempotencyKeyReused = 'IDEMPOTENCY_KEY_REUSED';
    case RateLimited = 'RATE_LIMITED';
    case InternalServerError = 'INTERNAL_SERVER_ERROR';
}
