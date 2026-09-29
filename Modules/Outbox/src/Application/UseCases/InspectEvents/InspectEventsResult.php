<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\UseCases\InspectEvents;

use Illuminate\Database\Eloquent\Collection;
use Modules\Outbox\Infrastructure\Persistence\Models\OutboxEventRecord;

final readonly class InspectEventsResult
{
    /** @param Collection<int, OutboxEventRecord> $data */
    public function __construct(public Collection $data) {}
}
