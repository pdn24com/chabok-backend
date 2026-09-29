<?php

declare(strict_types=1);

namespace Modules\Foundation\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\NodeAccessValidatorInterface;
use Modules\Foundation\Domain\Enums\SourceClient;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Symfony\Component\HttpFoundation\Response;

final readonly class ValidateNodeAccess
{
    public function __construct(private NodeAccessValidatorInterface $nodeAccessValidator, private AuditWriterInterface $auditWriter) {}

    public function handle(Request $request, Closure $next): Response
    {
        $nodeId = $request->attributes->get('node_id');
        $principal = $request->attributes->get('principal');
        if (is_string($nodeId) && $principal instanceof AuthenticatedPrincipal) {
            $this->nodeAccessValidator->assertAccessible($principal, $nodeId);
            $this->auditWriter->write($principal->hqId, $principal->userId, 'NODE_CONTEXT_SELECTED', 'NODE', $nodeId, (string) $request->attributes->get('correlation_id'), sourceClient: SourceClient::BranchPanel->value);
        }

        return $next($request);
    }
}
