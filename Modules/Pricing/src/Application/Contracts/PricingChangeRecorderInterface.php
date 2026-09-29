<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface PricingChangeRecorderInterface
{
    public function record(AuthenticatedPrincipal $actor, string $action, string $type, string $id, string $correlationId, array $after): void;
}
