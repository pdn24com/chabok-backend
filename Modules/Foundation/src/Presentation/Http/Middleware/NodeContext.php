<?php

declare(strict_types=1);

namespace Modules\Foundation\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

final class NodeContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $nodeId = trim((string) $request->headers->get('X-Node-Id', ''));
        if ($nodeId !== '' && (preg_match('/^[1-9][0-9]*$/D', $nodeId) !== 1 || (float) $nodeId > 4294967295)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', ['X-Node-Id' => ['common.node_id_must_be_integer']]);
        }
        $request->attributes->set('node_id', $nodeId !== '' ? $nodeId : null);

        return $next($request);
    }
}
