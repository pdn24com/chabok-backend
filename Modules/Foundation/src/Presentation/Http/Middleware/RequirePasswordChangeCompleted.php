<?php

declare(strict_types=1);

namespace Modules\Foundation\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Symfony\Component\HttpFoundation\Response;

final class RequirePasswordChangeCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $principal = $request->attributes->get('principal');
        if ($principal instanceof AuthenticatedPrincipal && $principal->mustChangePassword && ! in_array($request->route()?->getName(), (array) config('chabok.forced_password_allowed_routes', []), true)) {
            throw new ApiException(ApiErrorCode::PasswordChangeRequired, 403, 'foundation.password_change_is_required');
        }

        return $next($request);
    }
}
