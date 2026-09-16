<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Repositories;

interface OperationalDirectoryRepository
{
    public function drivers(?string $hqId, string $nodeId, ?string $capability): array;

    public function vehicles(?string $hqId, string $nodeId): array;

    public function routes(?string $hqId): array;

    public function activeNode(string $hqId, string $nodeId): ?object;

    public function insertRoute(array $attributes): void;

    public function insertLeg(array $attributes): void;
}
