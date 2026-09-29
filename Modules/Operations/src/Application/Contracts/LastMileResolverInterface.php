<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface LastMileResolverInterface
{
    public function resolveLastMile(AuthenticatedPrincipal $actor, string $gatewayNodeId, object $consignment): void;
}
