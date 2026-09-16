<?php

declare(strict_types=1);

namespace Modules\Foundation\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Symfony\Component\HttpFoundation\Response;

final class RequireSecureTransport
{
    public function handle(Request $request, Closure $next): Response
    {
        $explicitHttpException = app()->environment(['local', 'testing', 'staging']) && (bool) config('chabok.branch_panel.local_http_allowed');
        if (!$request->isSecure() && !$explicitHttpException) {
            throw new ApiException(ApiErrorCode::Forbidden, 403, 'Secure transport is required.');
        }
        return $next($request);
    }
}
