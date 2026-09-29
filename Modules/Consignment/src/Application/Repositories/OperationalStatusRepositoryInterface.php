<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Consignment\Infrastructure\Persistence\Models\StatusRecord;

interface OperationalStatusRepositoryInterface
{
    /** @return Collection<int, StatusRecord> */
    public function visibleTo(?string $hqId): Collection;

    /** @return list<string> */
    public function visibleCodes(?string $hqId, bool $manifestOnly): array;

    public function lockById(string $statusId): ?StatusRecord;

    public function codeTaken(string $code, ?string $hqId, bool $global): bool;

    /** Serializes catalogue writes so a global and a tenant code cannot claim the same namespace at once. */
    public function lockCatalogue(): void;
}
