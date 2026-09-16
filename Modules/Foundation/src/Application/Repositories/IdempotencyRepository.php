<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Repositories;

interface IdempotencyRepository
{
    public function lockActor(string $actorId): void;

    public function lockRecord(string $actorId, string $command, string $key): ?object;

    public function insert(array $attributes): void;

    public function update(string $recordId, array $changes): void;

    public function delete(string $recordId): void;
}
