<?php

declare(strict_types=1);

namespace Modules\Foundation\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Foundation\Application\Contracts\AccessTokenServiceInterface;
use Modules\Foundation\Application\Ports\AccessSessionValidatorInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthenticateAccessToken
{
    public function __construct(
        private AccessTokenServiceInterface $accessTokenService,
        private AccessSessionValidatorInterface $accessSessionValidator,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $authorization = (string) $request->headers->get('Authorization', '');
        if (! preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            throw new ApiException(ApiErrorCode::AuthenticationRequired, 401, 'common.authentication_required');
        }
        $principal = $this->accessSessionValidator->validate($this->accessTokenService->decode($matches[1]));
        $request->attributes->set('principal', $principal);
        $request->attributes->set('tenant_id', $principal->hqId);

        return $next($request);
    }
}
