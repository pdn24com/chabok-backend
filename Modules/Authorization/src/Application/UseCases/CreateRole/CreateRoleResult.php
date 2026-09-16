<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\CreateRole;

final readonly class CreateRoleResult
{
    public function __construct(public array $data)
    {
    }
}
