<?php

declare(strict_types=1);

namespace Modules\Iam\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Foundation\Application\Contracts\AccessTokenServiceInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AccessSessionValidatorInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Iam\Application\Ports\PlatformContextValidatorInterface;
use Modules\Iam\Application\Repositories\SessionRepositoryInterface;
use Modules\Iam\Domain\Support\OpaqueToken;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class AuthenticateLogoutAll
{
    public function __construct(
        private AccessTokenServiceInterface $accessTokenService,
        private AccessSessionValidatorInterface $accessSessionValidator,
        private PlatformContextValidatorInterface $platformContextValidator,
        private ClockInterface $clock,
        private SessionRepositoryInterface $sessionRepository,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $principal = $this->fromAccessToken($request) ?? $this->fromRefreshCookie($request);
        if ($principal === null) {
            throw new ApiException(ApiErrorCode::AuthenticationRequired, 401, 'common.authentication_required');
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
            return $this->accessSessionValidator->validate($this->accessTokenService->decode($matches[1]));
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
        $row = $this->sessionRepository->findLiveWithActiveUser(OpaqueToken::hash($raw), $this->clock->now());
        if ($row === null || $row->hq_id === null && ! $this->platformContextValidator->hasActivePlatformAssignment((string) $row->user_id)) {
            return null;
        }

        return new AuthenticatedPrincipal((string) $row->user_id, (string) $row->session_id, $row->hq_id === null ? null : (string) $row->hq_id, (bool) $row->user->must_change_password);
    }
}
