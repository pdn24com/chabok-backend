<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Dto;

final readonly class UserAssignmentDto
{
    public function __construct(
        public string $assignmentId,
        public string $userId,
        public string $roleId,
        public string $scopeType,
        public ?string $scopeId,
        public string $scopeTitle,
        public bool $includesDescendants,
        public string $status,
    ) {}
}
