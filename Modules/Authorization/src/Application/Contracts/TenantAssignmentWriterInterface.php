<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Contracts;

use Modules\Authorization\Infrastructure\Persistence\Models\AssignmentRecord;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ValueObjects\RoleAssignment;

interface TenantAssignmentWriterInterface
{
    /** @param list<RoleAssignment> $inputs @return list<AssignmentRecord> */
    public function createTenantAssignments(AuthenticatedPrincipal $actor, string $userId, string $hqId, array $inputs): array;
}
