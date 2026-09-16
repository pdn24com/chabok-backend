<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\GetUser;

final readonly class GetUserResult
{
    public function __construct(public array $data)
    {
    }
}
