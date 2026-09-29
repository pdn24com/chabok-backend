<?php

declare(strict_types=1);

namespace Modules\Foundation\Presentation\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Validation\ValidationException;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

final class ApiExceptionRenderer
{
    public static function render(Throwable $throwable, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            $throwable instanceof ApiException => ApiResponder::error($request, $throwable->errorCode, $throwable->messageKey, $throwable->httpStatus, $throwable->fieldErrors, $throwable->details, $throwable->messageParams),
            $throwable instanceof ValidationException => ApiResponder::error($request, ApiErrorCode::ValidationError, 'foundation.request_is_invalid', 422, $throwable->errors()),
            $throwable instanceof AuthenticationException => ApiResponder::error($request, ApiErrorCode::AuthenticationRequired, 'common.authentication_required', 401),
            $throwable instanceof AuthorizationException => ApiResponder::error($request, ApiErrorCode::Forbidden, 'common.access_denied', 403),
            $throwable instanceof TooManyRequestsHttpException => ApiResponder::error($request, ApiErrorCode::RateLimited, 'common.too_many_requests', 429),
            $throwable instanceof NotFoundHttpException => ApiResponder::error($request, ApiErrorCode::ResourceNotFound, 'common.resource_not_found', 404),
            $throwable instanceof MethodNotAllowedHttpException => ApiResponder::error($request, ApiErrorCode::MethodNotAllowed, 'common.method_not_allowed', 405),
            $throwable instanceof InvalidSignatureException => ApiResponder::error($request, ApiErrorCode::AuthenticationRequired, 'common.authentication_required', 401),
            $throwable instanceof QueryException && self::isIntegrityConflict($throwable) => ApiResponder::error($request, ApiErrorCode::Conflict, 'common.requested_change_conflicts_with_existing_data', 409),
            default => ApiResponder::error($request, ApiErrorCode::InternalServerError, 'common.unexpected_error_occurred', 500),
        };
    }

    private static function isIntegrityConflict(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }
}
