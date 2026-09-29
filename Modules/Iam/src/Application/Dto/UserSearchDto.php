<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Dto;

final readonly class UserSearchDto
{
    public function __construct(
        public ?string $status = null,
        public ?string $search = null,
        public int $page = 1,
        public int $pageSize = 25,
    ) {}
}
