<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ListPermissions;

final readonly class ListPermissionsResult
{
    public function __construct(public array $data)
    {
    }
}
