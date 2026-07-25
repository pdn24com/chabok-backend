<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Symfony\Component\HttpFoundation\Response;

final class RequireExactOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = (string) $request->headers->get('Origin', '');
        $origins = (array) config('chabok.branch_panel.origins', []);

        if ($origin === '' || ! in_array($origin, $origins, true)) {
            throw new ApiException(
                ApiErrorCode::OriginNotAllowed,
                403,
                'Origin is not allowed.',
            );
        }

        return $next($request);
    }
}
