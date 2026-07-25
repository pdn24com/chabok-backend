<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Foundation\Application\Contracts\NodeAccessValidator;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Symfony\Component\HttpFoundation\Response;

final readonly class ValidateNodeAccess
{
    public function __construct(private NodeAccessValidator $validator) {}

    public function handle(Request $request, Closure $next): Response
    {
        $nodeId = $request->attributes->get('node_id');
        $principal = $request->attributes->get('principal');
        if (is_string($nodeId) && $principal instanceof AuthenticatedPrincipal) {
            $this->validator->assertAccessible($principal, $nodeId);
        }

        return $next($request);
    }
}
