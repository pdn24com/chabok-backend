<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Foundation\Application\ApiResponder;
use Modules\Foundation\Domain\ApiErrorCode;
use Symfony\Component\HttpFoundation\Response;

final class CorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = trim((string) $request->headers->get('X-Correlation-ID', ''));
        $correlationId = $provided !== '' ? $provided : (string) Str::uuid();
        $request->attributes->set('correlation_id', $correlationId);

        if ($provided !== '' && ! Str::isUuid($provided)) {
            $response = ApiResponder::error(
                $request,
                ApiErrorCode::ValidationError,
                'The request is invalid.',
                422,
                ['X-Correlation-ID' => ['The X-Correlation-ID field must be a UUID.']],
            );
        } else {
            $response = $next($request);
        }

        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
