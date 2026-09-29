<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Contracts;

interface CustomerOrgStructureValidatorInterface
{
    /** Refuses a chart on a record that is not a company, which has no chart of its own. */
    public function assertCompany(string $hqId, string $customerId): void;

    /**
     * Refuses a parent that belongs to another company or that sits under the node being moved. The
     * database only rejects a node pointing straight at itself, so the longer cycles are caught here.
     */
    public function assertParent(string $hqId, string $customerId, ?string $parentDepartmentId, ?string $movingDepartmentId = null): void;
}
