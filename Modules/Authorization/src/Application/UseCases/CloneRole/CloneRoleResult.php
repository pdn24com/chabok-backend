<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\CloneRole;

final readonly class CloneRoleResult
{
    public function __construct(public array $data)
    {
    }
}
