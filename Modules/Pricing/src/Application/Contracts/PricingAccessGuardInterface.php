<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface PricingAccessGuardInterface
{
    public function assertAccess(AuthenticatedPrincipal $actor, string $permission, bool $runtime = false): void;
}
