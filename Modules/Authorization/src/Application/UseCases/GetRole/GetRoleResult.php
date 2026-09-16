<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\GetRole;

final readonly class GetRoleResult
{
    public function __construct(public array $data)
    {
    }
}
