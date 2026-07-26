<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Foundation\Application\Contracts\NodeAccessValidator;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Symfony\Component\HttpFoundation\Response;

final readonly class ValidateNodeAccess
{
    public function __construct(
        private NodeAccessValidator $validator,
        private AuditWriter $audit,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $nodeId = $request->attributes->get('node_id');
        $principal = $request->attributes->get('principal');
        if (is_string($nodeId) && $principal instanceof AuthenticatedPrincipal) {
            $this->validator->assertAccessible($principal, $nodeId);
            $this->audit->write(
                $principal->hqId,
                $principal->userId,
                'NODE_CONTEXT_SELECTED',
                'NODE',
                $nodeId,
                (string) $request->attributes->get('correlation_id'),
                sourceClient: 'BRANCH_PANEL',
            );
        }

        return $next($request);
    }
}
