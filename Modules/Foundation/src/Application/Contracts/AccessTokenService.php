<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Contracts;

use Modules\Foundation\Domain\AccessTokenClaims;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

interface AccessTokenService
{
    /**
     * @return array{token: string, expires_in: int}
     */
    public function issue(AuthenticatedPrincipal $principal): array;

    public function decode(string $token): AccessTokenClaims;
}
