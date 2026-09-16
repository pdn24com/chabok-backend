<?php

declare(strict_types=1);

namespace Modules\Foundation\Presentation\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Validation\ValidationException;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

final class ApiExceptionRenderer
{
    public static function render(Throwable $throwable, Request $request): mixed
    {
        if (!$request->is('api/*')) {
            return null;
        }
        return match (true) {
            $throwable instanceof ApiException => ApiResponder::error($request, $throwable->errorCode, $throwable->getMessage(), $throwable->httpStatus, $throwable->fieldErrors, $throwable->details),
            $throwable instanceof ValidationException => ApiResponder::error($request, ApiErrorCode::ValidationError, 'The request is invalid.', 422, $throwable->errors()),
            $throwable instanceof AuthenticationException => ApiResponder::error($request, ApiErrorCode::AuthenticationRequired, 'Authentication required.', 401),
            $throwable instanceof AuthorizationException => ApiResponder::error($request, ApiErrorCode::Forbidden, 'Access denied.', 403),
            $throwable instanceof TooManyRequestsHttpException => ApiResponder::error($request, ApiErrorCode::RateLimited, 'Too many requests.', 429),
            $throwable instanceof NotFoundHttpException => ApiResponder::error($request, ApiErrorCode::ResourceNotFound, 'Resource not found.', 404),
            $throwable instanceof MethodNotAllowedHttpException => ApiResponder::error($request, ApiErrorCode::MethodNotAllowed, 'Method not allowed.', 405),
            $throwable instanceof InvalidSignatureException => ApiResponder::error($request, ApiErrorCode::AuthenticationRequired, 'Authentication required.', 401),
            $throwable instanceof QueryException && self::isIntegrityConflict($throwable) => ApiResponder::error($request, ApiErrorCode::Conflict, 'The requested change conflicts with existing data.', 409),
            default => ApiResponder::error($request, ApiErrorCode::InternalServerError, 'An unexpected error occurred.', 500),
        };
    }

    private static function isIntegrityConflict(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }
}
