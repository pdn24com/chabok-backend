<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\UseCases\RecordActivity;

use Modules\CrmTask\Infrastructure\Persistence\Models\ActivityParticipantRecord;
use Modules\CrmTask\Infrastructure\Persistence\Models\ActivityRecord;

/** The interaction as recorded, and the colleagues written beside it. */
final readonly class RecordActivityResult
{
    /** @param list<ActivityParticipantRecord> $participants */
    public function __construct(
        public ActivityRecord $activity,
        public array $participants,
    ) {}
}
