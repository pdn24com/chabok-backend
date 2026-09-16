<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\RefreshSession;

final readonly class RefreshSessionResult
{
    public function __construct(public array $data)
    {
    }
}
