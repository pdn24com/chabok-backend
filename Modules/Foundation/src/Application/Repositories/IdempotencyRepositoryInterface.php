<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Repositories;

use Modules\Foundation\Infrastructure\Persistence\Models\IdempotencyRecord;

interface IdempotencyRepositoryInterface
{
    /** Locks the actor's record for this command and key so two concurrent retries cannot both execute. */
    public function lockForCommand(string $actorId, string $commandName, string $key): ?IdempotencyRecord;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): IdempotencyRecord;

    /** @param array<string, mixed> $changes */
    public function apply(IdempotencyRecord $record, array $changes): void;

    public function delete(IdempotencyRecord $record): void;
}
