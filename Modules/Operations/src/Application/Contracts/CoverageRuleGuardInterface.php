<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Operations\Application\Dto\CoverageRuleDto;

interface CoverageRuleGuardInterface
{
    /** @param list<CoverageRuleDto> $rules */
    public function validateRuleInputs(string $hq, array $rules): void;
}
