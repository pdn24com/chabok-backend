<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Ports;

use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

/**
 * Port owned by Foundation, implemented by the Authorization module.
 *
 * @see Modules/Authorization/src/Infrastructure/Adapters/AccessContextAdapter.php (bound in AuthorizationServiceProvider)
 */
interface AccessContextResolverInterface
{
    public function resolve(AuthenticatedPrincipal $principal): AccessContextDto;
}
