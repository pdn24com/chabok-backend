<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\ListUsers;

use Modules\Foundation\Application\Data\Page;

final readonly class ListUsersResult
{
    public function __construct(public Page $data)
    {
    }
}
