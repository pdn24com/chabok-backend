<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Contracts;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

interface AuthorizationContextResolver
{
    /** @return array<string, mixed> */
    public function resolve(AuthenticatedPrincipal $principal): array;
}
