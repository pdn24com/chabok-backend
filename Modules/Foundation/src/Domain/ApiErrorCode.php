<?php

declare(strict_types=1);

namespace Modules\Foundation\Domain;

enum ApiErrorCode: string
{
    case ValidationError = 'VALIDATION_ERROR';
    case AuthenticationRequired = 'AUTHENTICATION_REQUIRED';
    case InvalidCredentials = 'INVALID_CREDENTIALS';
    case Forbidden = 'FORBIDDEN';
    case OriginNotAllowed = 'ORIGIN_NOT_ALLOWED';
    case PasswordChangeRequired = 'PASSWORD_CHANGE_REQUIRED';
    case ResourceNotFound = 'RESOURCE_NOT_FOUND';
    case MethodNotAllowed = 'METHOD_NOT_ALLOWED';
    case Conflict = 'CONFLICT';
    case RateLimited = 'RATE_LIMITED';
    case InternalServerError = 'INTERNAL_SERVER_ERROR';
}
