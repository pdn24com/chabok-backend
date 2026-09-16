<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\UpdateRole;

final readonly class UpdateRoleResult
{
    public function __construct(public array $data)
    {
    }
}
