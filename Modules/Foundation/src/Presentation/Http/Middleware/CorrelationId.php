<?php

declare(strict_types=1);

namespace Modules\Foundation\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Domain\ApiErrorCode;
use Symfony\Component\HttpFoundation\Response;

final class CorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = trim((string) $request->headers->get('X-Correlation-ID', ''));
        $correlationId = $provided !== '' ? $provided : (string) Str::uuid();
        $request->attributes->set('correlation_id', $correlationId);
        $startedAt = hrtime(true);
        Log::shareContext([
            'correlation_id' => $correlationId,
            'request_method' => $request->method(),
            'request_path' => '/' . $request->path(),
        ]);
        try {
            if ($provided !== '' && !Str::isUuid($provided)) {
                $response = ApiResponder::error($request, ApiErrorCode::ValidationError, 'The request is invalid.', 422, ['X-Correlation-ID' => ['The X-Correlation-ID field must be a UUID.']]);
            } else {
                $response = $next($request);
            }
            $response->headers->set('X-Correlation-ID', $correlationId);
            Log::info('http.request.completed', ['status' => $response->getStatusCode(), 'duration_ms' => round((hrtime(true) - $startedAt) / 1000000, 2)]);
            return $response;
        } finally {
            Log::flushSharedContext();
        }
    }
}
