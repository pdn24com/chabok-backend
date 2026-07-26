<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Application\Contracts\SecurityMetricRecorder;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireExactOrigin
{
    public function __construct(private SecurityMetricRecorder $metrics) {}

    public function handle(Request $request, Closure $next): Response
    {
        $origin = (string) $request->headers->get('Origin', '');
        $origins = (array) config('chabok.branch_panel.origins', []);

        if ($origin === '' || ! in_array($origin, $origins, true)) {
            $this->metrics->increment('auth.origin_rejected', [
                'reason' => $origin === '' ? 'MISSING' : 'UNLISTED',
            ]);
            throw new ApiException(
                ApiErrorCode::OriginNotAllowed,
                403,
                'Origin is not allowed.',
            );
        }

        return $next($request);
    }
}
