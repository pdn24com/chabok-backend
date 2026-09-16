<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ClonePricingDraft;

final readonly class ClonePricingDraftResult
{
    public function __construct(public array $data)
    {
    }
}
