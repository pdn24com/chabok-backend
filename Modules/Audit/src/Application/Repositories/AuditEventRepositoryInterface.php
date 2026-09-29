<?php

declare(strict_types=1);

namespace Modules\Audit\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Audit\Infrastructure\Persistence\Models\AuditEventRecord;

interface AuditEventRepositoryInterface
{
    /**
     * The change trail of one resource, newest first, with the user who caused each entry.
     *
     * @return Collection<int, AuditEventRecord>
     */
    public function historyForResource(string $hqId, string $resourceType, string $resourceId): Collection;
}
