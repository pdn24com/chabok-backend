<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Adapters;

use Modules\Authorization\Application\AuthorizationService;
use Modules\Foundation\Application\Contracts\NodeAccessValidator;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class AuthorizationNodeAccessValidator implements NodeAccessValidator
{
    public function __construct(private AuthorizationService $authorization) {}

    public function assertAccessible(AuthenticatedPrincipal $principal, string $nodeId): void
    {
        $this->authorization->assertNodeAccessible($principal, $nodeId);
    }
}
