<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\CreateUser;

final readonly class CreateUserResult
{
    public function __construct(public array $data)
    {
    }
}
