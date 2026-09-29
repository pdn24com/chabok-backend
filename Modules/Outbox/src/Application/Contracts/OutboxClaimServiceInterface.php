<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\Contracts;

use Modules\Outbox\Infrastructure\Persistence\Models\OutboxEventRecord;

interface OutboxClaimServiceInterface
{
    /** @return list<OutboxEventRecord> */
    public function claim(int $limit, string $workerId): array;
}
