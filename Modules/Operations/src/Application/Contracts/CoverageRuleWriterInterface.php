<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Operations\Application\Dto\CoverageRuleDto;

interface CoverageRuleWriterInterface
{
    /** @param list<CoverageRuleDto> $rules */
    public function replaceRules(string $hq, string $versionId, array $rules): void;
}
