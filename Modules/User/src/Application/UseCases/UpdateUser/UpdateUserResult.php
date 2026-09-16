<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\UpdateUser;

final readonly class UpdateUserResult
{
    public function __construct(public array $data)
    {
    }
}
