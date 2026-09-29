<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\GetAssignmentOptions;

use Modules\Authorization\Application\Dto\AssignmentAreaOptionDto;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final readonly class GetAssignmentOptionsResult
{
    /** @param list<AssignmentAreaOptionDto> $areas @param list<NodeRecord> $nodes */
    public function __construct(public bool $tenantAllowed, public array $areas, public array $nodes) {}
}
