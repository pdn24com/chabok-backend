<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Contracts;

use Modules\Foundation\Domain\AccessTokenClaims;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

interface AccessSessionValidator
{
    public function validate(AccessTokenClaims $claims): AuthenticatedPrincipal;
}
