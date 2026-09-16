<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\RefreshSession;

final readonly class RefreshSessionCommand
{
    public function __construct(public string $rawToken, public string $correlationId, public ?string $ip, public ?string $userAgent)
    {
    }
}
