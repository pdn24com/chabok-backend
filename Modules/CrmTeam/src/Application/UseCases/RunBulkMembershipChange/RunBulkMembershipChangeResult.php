<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\UseCases\RunBulkMembershipChange;

use Modules\CrmTeam\Application\Dto\BulkMembershipPreviewDto;
use Modules\CrmTeam\Infrastructure\Persistence\Models\MembershipEventRecord;

final readonly class RunBulkMembershipChangeResult
{
    /** @param list<MembershipEventRecord> $events */
    public function __construct(
        public BulkMembershipPreviewDto $preview,
        public array $events = [],
    ) {}
}
