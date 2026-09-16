<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\ListSessions;

final readonly class ListSessionsResult
{
    public function __construct(public array $data)
    {
    }
}
