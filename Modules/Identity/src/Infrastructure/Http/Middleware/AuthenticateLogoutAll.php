<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Foundation\Application\Contracts\AccessSessionValidator;
use Modules\Foundation\Application\Contracts\AccessTokenService;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Identity\Application\Contracts\PlatformContextValidator;
use Modules\Identity\Domain\OpaqueToken;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class AuthenticateLogoutAll
{
    public function __construct(
        private AccessTokenService $tokens,
        private AccessSessionValidator $sessions,
        private PlatformContextValidator $platformContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $principal = $this->fromAccessToken($request) ?? $this->fromRefreshCookie($request);
        if ($principal === null) {
            throw new ApiException(ApiErrorCode::AuthenticationRequired, 401, 'Authentication required.');
        }

        $request->attributes->set('principal', $principal);
        $request->attributes->set('tenant_id', $principal->hqId);

        return $next($request);
    }

    private function fromAccessToken(Request $request): ?AuthenticatedPrincipal
    {
        $authorization = (string) $request->header('Authorization', '');
        if (! preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return null;
        }
        try {
            return $this->sessions->validate($this->tokens->decode($matches[1]));
        } catch (Throwable) {
            return null;
        }
    }

    private function fromRefreshCookie(Request $request): ?AuthenticatedPrincipal
    {
        $raw = (string) $request->cookie((string) config('chabok.refresh_cookie.name'), '');
        if ($raw === '') {
            return null;
        }
        $row = DB::table('user_sessions as s')
            ->join('users as u', 'u.user_id', '=', 's.user_id')
            ->where('s.refresh_token_hash', OpaqueToken::hash($raw))
            ->whereNull('s.revoked_at')
            ->where('s.expires_at', '>', now())
            ->where('u.status', 'ACTIVE')
            ->select(['s.session_id', 's.user_id', 's.hq_id', 'u.must_change_password'])
            ->first();
        if (
            $row === null
            || ($row->hq_id === null && ! $this->platformContext->hasActivePlatformAssignment((string) $row->user_id))
        ) {
            return null;
        }

        return new AuthenticatedPrincipal(
            (string) $row->user_id,
            (string) $row->session_id,
            $row->hq_id === null ? null : (string) $row->hq_id,
            (bool) $row->must_change_password,
        );
    }
}
