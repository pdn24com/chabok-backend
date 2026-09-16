<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ListRoles;

final readonly class ListRolesResult
{
    public function __construct(public array $data)
    {
    }
}
