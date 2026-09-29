<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;

interface PricingAuditRepositoryInterface
{
    /** Pricing audit events of one tenant, newest first. @return LengthAwarePaginator<\Modules\Audit\Infrastructure\Persistence\Models\AuditEventRecord> */
    public function paginatePricingEvents(?string $hqId, ?string $targetId, int $page, int $pageSize): LengthAwarePaginator;
}
