<?php

declare(strict_types=1);

namespace Modules\Foundation\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Foundation\Application\Contracts\AccessSessionValidator;
use Modules\Foundation\Application\Contracts\AccessTokenService;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthenticateAccessToken
{
    public function __construct(private AccessTokenService $tokens, private AccessSessionValidator $sessions)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $authorization = (string) $request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            throw new ApiException(ApiErrorCode::AuthenticationRequired, 401, 'Authentication required.');
        }
        $principal = $this->sessions->validate($this->tokens->decode($matches[1]));
        $request->attributes->set('principal', $principal);
        $request->attributes->set('tenant_id', $principal->hqId);
        return $next($request);
    }
}
