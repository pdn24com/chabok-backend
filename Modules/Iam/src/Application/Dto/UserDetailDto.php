<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Dto;

use Modules\Foundation\Application\Dto\SessionSummaryDto;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

final readonly class UserDetailDto
{
    /** @param list<UserAssignmentDto> $assignments @param list<SessionSummaryDto> $sessions */
    public function __construct(
        public UserRecord $user,
        public array $assignments,
        public ?string $invitationStatus,
        public ?DriverProfileSummaryDto $driverProfile,
        public array $sessions,
    ) {}
}
