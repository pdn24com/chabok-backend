<?php

declare(strict_types=1);

namespace Modules\CrmTask\Infrastructure\Repositories;

use Modules\CrmTask\Application\Repositories\ActivityParticipantRepositoryInterface;
use Modules\CrmTask\Infrastructure\Persistence\Models\ActivityParticipantRecord;

final class EloquentActivityParticipantRepository implements ActivityParticipantRepositoryInterface
{
    public function create(array $attributes): ActivityParticipantRecord
    {
        return ActivityParticipantRecord::query()->forceCreate($attributes);
    }
}
