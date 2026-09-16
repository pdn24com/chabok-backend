<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\GetPricingHistory;

final readonly class GetPricingHistoryResult
{
    public function __construct(public array $data)
    {
    }
}
