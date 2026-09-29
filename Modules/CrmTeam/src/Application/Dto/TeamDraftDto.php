<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\Dto;

use Modules\CrmTeam\Domain\Enums\TeamStatus;

final readonly class TeamDraftDto
{
    public function __construct(
        public string $title,
        public string $supervisorUserId,
        public TeamStatus $status = TeamStatus::ACTIVE,
        /** Reserved by the model; building a new hierarchy is not required by this version. */
        public ?string $parentTeamId = null,
    ) {}
}
