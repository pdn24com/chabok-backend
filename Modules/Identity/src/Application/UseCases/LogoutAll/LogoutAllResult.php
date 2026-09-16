<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\LogoutAll;

final readonly class LogoutAllResult
{
    public function __construct(public int $data)
    {
    }
}
