<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Contracts;

use Modules\Foundation\Application\Dto\IssuedAccessTokenDto;
use Modules\Foundation\Domain\ValueObjects\AccessTokenClaims;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface AccessTokenServiceInterface
{
    public function issue(AuthenticatedPrincipal $principal): IssuedAccessTokenDto;

    public function decode(string $token): AccessTokenClaims;
}
