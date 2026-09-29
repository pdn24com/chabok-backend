<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Dto;

/** A new node in the department chart of a customer company. */
final readonly class CustomerDepartmentDraftDto
{
    public function __construct(
        public string $title,
        /** Null puts the node at the root of the company chart. */
        public ?string $parentDepartmentId = null,
        public ?string $costCenterCode = null,
    ) {}

    public function toAttributes(): array
    {
        return [
            'title' => $this->title,
            'parent_department_id' => $this->parentDepartmentId,
            'cost_center_code' => $this->costCenterCode,
        ];
    }
}
