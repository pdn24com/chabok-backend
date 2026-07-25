<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Symfony\Component\HttpFoundation\Response;

final class RequirePasswordChangeCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $principal = $request->attributes->get('principal');

        if (
            $principal instanceof AuthenticatedPrincipal &&
            $principal->mustChangePassword &&
            ! in_array(
                $request->route()?->getName(),
                (array) config('chabok.forced_password_allowed_routes', []),
                true,
            )
        ) {
            throw new ApiException(
                ApiErrorCode::PasswordChangeRequired,
                403,
                'Password change is required.',
            );
        }

        return $next($request);
    }
}
