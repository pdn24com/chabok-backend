<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Dto;

use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Domain\ValueObjects\ScopeCoverage;

final readonly class DelegationSnapshotDto
{
    /** @param list<string> $permissionCodes */
    public function __construct(public array $permissionCodes, public AccessContextDto $context, public ScopeCoverage $coverage) {}
}
