<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\UseCases\ExecuteIdempotentCommand;

final readonly class ExecuteIdempotentCommandResult
{
    public function __construct(
        public int $status,
        public string $body,
        public bool $replayed = false,
    ) {}
}
