<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\ListUsers;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListUsersCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public int $page,
        public int $pageSize,
        public ?string $search,
        public ?string $status,
        public ?string $nodeId = null,
    ) {}
}
