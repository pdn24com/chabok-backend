<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Repositories;

use Modules\Customer\Infrastructure\Persistence\Models\CustomerIndustryRecord;

interface CustomerIndustryRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): CustomerIndustryRecord;

    public function linkExists(string $hqId, string $customerId, string $industryId): bool;

    public function clearPrimary(string $hqId, string $customerId): void;

    public function markPrimary(string $hqId, string $customerId, string $industryId): void;
}
