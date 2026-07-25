<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Symfony\Component\HttpFoundation\Response;

final class CredentialedCors
{
    private const ALLOWED_HEADERS = 'Authorization, Content-Type, X-Correlation-ID, X-Node-Id, Idempotency-Key';

    public function handle(Request $request, Closure $next): Response
    {
        $origin = (string) $request->headers->get('Origin', '');
        $allowed = $origin !== '' && in_array(
            $origin,
            (array) config('chabok.branch_panel.origins', []),
            true,
        );

        if ($request->isMethod('OPTIONS') && $request->headers->has('Access-Control-Request-Method')) {
            if (! $allowed) {
                throw new ApiException(
                    ApiErrorCode::OriginNotAllowed,
                    403,
                    'Origin is not allowed.',
                );
            }

            $response = response('', 204);
        } else {
            $response = $next($request);
        }

        $response->headers->set('Vary', 'Origin');

        if ($allowed) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Allow-Headers', self::ALLOWED_HEADERS);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PATCH, PUT, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Expose-Headers', 'X-Correlation-ID');
        }

        return $response;
    }
}
