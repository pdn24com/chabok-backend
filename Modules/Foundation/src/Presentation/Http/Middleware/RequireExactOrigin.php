<?php

declare(strict_types=1);

namespace Modules\Foundation\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Foundation\Application\Contracts\SecurityMetricRecorderInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireExactOrigin
{
    public function __construct(private SecurityMetricRecorderInterface $securityMetricRecorder) {}

    public function handle(Request $request, Closure $next): Response
    {
        $origin = (string) $request->headers->get('Origin', '');
        $origins = (array) config('chabok.branch_panel.origins', []);
        if ($origin === '' || ! in_array($origin, $origins, true)) {
            $this->securityMetricRecorder->increment('auth.origin_rejected', ['reason' => $origin === '' ? 'MISSING' : 'UNLISTED']);
            throw new ApiException(ApiErrorCode::OriginNotAllowed, 403, 'foundation.origin_is_not_allowed');
        }

        return $next($request);
    }
}
