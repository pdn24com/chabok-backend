<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;

final readonly class ResolvedServiceTariffDto
{
    public function __construct(public TariffVersionRecord $version, public string $chargeCode) {}
}
