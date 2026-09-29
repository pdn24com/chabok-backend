<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\ListSessions;

final readonly class ListSessionsCommand
{
    public function __construct(public string $userId) {}
}
