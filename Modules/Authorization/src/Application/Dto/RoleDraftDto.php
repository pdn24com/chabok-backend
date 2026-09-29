<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Dto;

final readonly class RoleDraftDto
{
    /** @param list<string>|null $permissionCodes @param list<string>|null $menuKeys */
    public function __construct(
        public string $code,
        public string $title,
        public ?string $description,
        public ?array $permissionCodes,
        public ?array $menuKeys,
        public bool $menuSpecified,
    ) {}
}
