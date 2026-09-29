<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

use Modules\Pricing\Application\Enums\ServiceDependencyFailure;

final readonly class ServiceTariffResolutionDto
{
    /** @param list<ResolvedServiceTariffDto> $tariffs */
    public function __construct(public array $tariffs = [], public ?ServiceDependencyFailure $failure = null) {}
}
