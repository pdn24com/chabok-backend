<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\SimulateTariffDraft;

final readonly class SimulateTariffDraftResult
{
    public function __construct(public array $data)
    {
    }
}
