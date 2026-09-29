<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Repositories;

interface ScheduleChildrenRepositoryInterface
{
    public function deleteWindows(string $versionId): void;

    public function deleteScopes(string $versionId): void;

    /** @param list<array<string, mixed>> $rows */
    public function insertWindows(array $rows): void;

    /** @param list<array<string, mixed>> $rows */
    public function insertScopes(array $rows): void;
}
