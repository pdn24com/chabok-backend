<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface UserAccessGuardInterface
{
    public function requireTenant(AuthenticatedPrincipal $actor): string;

    public function assertTenantUser(bool $exists, ?string $userHqId, string $hqId): void;
}
