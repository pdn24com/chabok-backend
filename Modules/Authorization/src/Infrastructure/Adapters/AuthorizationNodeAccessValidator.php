<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Adapters;

use Modules\Authorization\Application\Contracts\AuthorizationGuardInterface;
use Modules\Foundation\Application\Ports\NodeAccessValidatorInterface;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class AuthorizationNodeAccessValidator implements NodeAccessValidatorInterface
{
    public function __construct(private AuthorizationGuardInterface $authorizationGuard) {}

    public function assertAccessible(AuthenticatedPrincipal $principal, string $nodeId): void
    {
        $this->authorizationGuard->assertNodeAccessible($principal, $nodeId);
    }
}
