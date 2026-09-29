<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

use Modules\Pricing\Domain\ValueObjects\CompiledMatrixRateRule;
use Modules\Pricing\Domain\ValueObjects\FreightMatrix;

interface FreightMatrixRuleCompilerInterface
{
    /** @param list<FreightMatrix> $matrices @return list<CompiledMatrixRateRule> */
    public function compile(array $matrices, string $chargeTypeId): array;
}
