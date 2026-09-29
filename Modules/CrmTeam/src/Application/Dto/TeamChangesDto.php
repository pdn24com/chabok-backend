<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\Dto;

use Modules\CrmTeam\Domain\Enums\TeamStatus;

final readonly class TeamChangesDto
{
    public function __construct(
        public ?string $title = null,
        public ?string $supervisorUserId = null,
        public ?TeamStatus $status = null,
        public ?string $parentTeamId = null,
        public bool $parentSpecified = false,
    ) {}

    public function touchesNothing(): bool
    {
        return $this->title === null && $this->supervisorUserId === null
            && $this->status === null && ! $this->parentSpecified;
    }
}
