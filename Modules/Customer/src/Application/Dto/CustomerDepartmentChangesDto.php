<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Dto;

/** A PATCH of one chart node; each nullable field carries its own flag so clearing differs from skipping. */
final readonly class CustomerDepartmentChangesDto
{
    public function __construct(
        public ?string $title = null,
        public ?string $parentDepartmentId = null,
        public bool $parentSpecified = false,
        public ?string $costCenterCode = null,
        public bool $costCenterSpecified = false,
    ) {}

    public function touchesNothing(): bool
    {
        return $this->title === null && ! $this->parentSpecified && ! $this->costCenterSpecified;
    }

    public function toAttributes(): array
    {
        $attributes = [];
        if ($this->title !== null) {
            $attributes['title'] = $this->title;
        }
        if ($this->parentSpecified) {
            $attributes['parent_department_id'] = $this->parentDepartmentId;
        }
        if ($this->costCenterSpecified) {
            $attributes['cost_center_code'] = $this->costCenterCode;
        }

        return $attributes;
    }
}
