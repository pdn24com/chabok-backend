<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Repositories;

interface OperationalStatusRepository
{
    public function entries(?string $hqId): array;

    public function codes(?string $hqId, bool $manifestOnly): array;

    public function lockCatalog(): ?object;

    public function lockStatus(string $id): ?object;

    public function codeExists(string $code, bool $global, ?string $hqId): bool;

    public function update(string $id, array $attributes): void;

    public function insert(array $attributes): void;

    public function appendRevision(array $attributes): void;
}
