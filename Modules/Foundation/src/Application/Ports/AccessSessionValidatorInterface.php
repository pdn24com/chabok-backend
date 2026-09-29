<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Ports;

use Modules\Foundation\Domain\ValueObjects\AccessTokenClaims;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

/**
 * Port owned by Foundation, implemented by the Iam module.
 *
 * @see Modules/Iam/src/Infrastructure/Security/DatabaseAccessSessionValidator.php (bound in IamServiceProvider)
 */
interface AccessSessionValidatorInterface
{
    public function validate(AccessTokenClaims $claims): AuthenticatedPrincipal;
}
