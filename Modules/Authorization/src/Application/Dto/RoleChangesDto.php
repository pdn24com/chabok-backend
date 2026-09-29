<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Dto;

use Modules\Authorization\Domain\Enums\RoleStatus;

final readonly class RoleChangesDto
{
    /** @param list<string>|null $permissionCodes @param list<string>|null $menuKeys */
    public function __construct(
        public ?string $title,
        public ?string $description,
        public bool $descriptionSpecified,
        public ?RoleStatus $status,
        public ?array $permissionCodes,
        public ?array $menuKeys,
        public bool $menuSpecified,
    ) {}

    public function attributes(): array
    {
        $attributes = [];
        if ($this->title !== null) {
            $attributes['role_title'] = $this->title;
        }
        if ($this->descriptionSpecified) {
            $attributes['description'] = $this->description;
        }
        if ($this->status !== null) {
            $attributes['status'] = $this->status->value;
        }

        return $attributes;
    }
}
