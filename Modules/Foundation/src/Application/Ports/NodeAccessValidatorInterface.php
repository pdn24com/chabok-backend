<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Ports;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

/**
 * Port owned by Foundation, implemented by the Authorization module.
 *
 * @see Modules/Authorization/src/Infrastructure/Adapters/AuthorizationNodeAccessValidator.php (bound in AuthorizationServiceProvider)
 */
interface NodeAccessValidatorInterface
{
    public function assertAccessible(AuthenticatedPrincipal $principal, string $nodeId): void;
}
