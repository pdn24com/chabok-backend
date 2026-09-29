<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Dto;

use Illuminate\Database\Eloquent\Collection;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerDepartmentRecord;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerRecord;

/**
 * The department chart of one customer company. The rows arrive flat with their posts already loaded;
 * the presentation nests them, so the tree is shaped once and the query count stays fixed.
 */
final readonly class CustomerOrgStructureDto
{
    /** @param Collection<int, CustomerDepartmentRecord> $departments */
    public function __construct(
        public CustomerRecord $company,
        public Collection $departments,
    ) {}
}
