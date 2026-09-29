<?php

declare(strict_types=1);

namespace Modules\Foundation\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Symfony\Component\HttpFoundation\Response;

final class CorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = trim((string) $request->headers->get('X-Correlation-ID', ''));
        $correlationId = $provided !== '' ? $provided : bin2hex(random_bytes(16));
        $request->attributes->set('correlation_id', $correlationId);
        $startedAt = hrtime(true);
        Log::shareContext([
            'correlation_id' => $correlationId,
            'request_method' => $request->method(),
            'request_path' => '/'.$request->path(),
        ]);
        try {
            if ($provided !== '' && preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $provided) !== 1) {
                $response = ApiResponder::error($request, ApiErrorCode::ValidationError, 'foundation.request_is_invalid', 422, ['X-Correlation-ID' => ['common.correlation_id_is_invalid']]);
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
