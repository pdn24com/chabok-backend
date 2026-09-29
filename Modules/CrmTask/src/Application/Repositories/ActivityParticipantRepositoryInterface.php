<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\Repositories;

use Modules\CrmTask\Infrastructure\Persistence\Models\ActivityParticipantRecord;

interface ActivityParticipantRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): ActivityParticipantRecord;
}
