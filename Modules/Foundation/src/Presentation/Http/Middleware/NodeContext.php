<?php

declare(strict_types=1);

namespace Modules\Foundation\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Symfony\Component\HttpFoundation\Response;

final class NodeContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $nodeId = trim((string) $request->headers->get('X-Node-Id', ''));
        if ($nodeId !== '' && !Str::isUuid($nodeId)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The request is invalid.', ['X-Node-Id' => ['The X-Node-Id field must be a UUID.']]);
        }
        $request->attributes->set('node_id', $nodeId !== '' ? $nodeId : null);
        return $next($request);
    }
}
