<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\TransitionPricingVersion;

final readonly class TransitionPricingVersionResult
{
    public function __construct(public array $data)
    {
    }
}
