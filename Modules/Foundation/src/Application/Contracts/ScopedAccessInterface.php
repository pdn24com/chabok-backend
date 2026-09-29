<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Contracts;

use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Domain\ValueObjects\ScopeCoverage;

interface ScopedAccessInterface
{
    public function areaIds(
        string $hqId,
        string $areaId,
        bool $descendants,
    ): array;

    public function nodes(
        AccessContextDto $context,
        string $permission,
        bool $activeOnly = true,
    ): array;

    public function coverage(string $hqId): ScopeCoverage;
}
