<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

use Modules\Pricing\Application\Dto\TariffDraftDto;

interface TariffMatrixCompilerInterface
{
    public function prepare(TariffDraftDto $input, array $zones, string $hqId): TariffDraftDto;
}
