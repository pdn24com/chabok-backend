<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

use Modules\Pricing\Domain\ValueObjects\FreightMatrix;

final readonly class TariffMatrixSelectionDto
{
    /** @param list<FreightMatrix> $matrices @param array<string, string> $zoneCodes */
    public function __construct(public array $matrices, public array $zoneCodes, public bool $serviceTariff) {}
}
