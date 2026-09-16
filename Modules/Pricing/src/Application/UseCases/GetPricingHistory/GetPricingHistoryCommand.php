<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\GetPricingHistory;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetPricingHistoryCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $kind, public string $identityId)
    {
    }
}
