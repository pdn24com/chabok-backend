<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\Logout;

final readonly class LogoutCommand
{
    public function __construct(public ?string $rawToken, public string $correlationId)
    {
    }
}
