<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\ListUsers;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListUsersCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public int $page,
        public int $pageSize,
        public ?string $search,
        public ?string $status,
        public ?string $nodeId = null,
    )
    {
    }
}
