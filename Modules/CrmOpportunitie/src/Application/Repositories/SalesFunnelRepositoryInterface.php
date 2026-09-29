<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\SalesFunnelRecord;

interface SalesFunnelRepositoryInterface
{
    /**
     * The funnels of a tenant with their steps in board order. A tenant keeps a handful of funnels, so
     * the whole set is read at once rather than a page of it.
     *
     * @return Collection<int, SalesFunnelRecord>
     */
    public function listForTenant(string $hqId, ?bool $active = null): Collection;

    public function findForTenant(string $hqId, string $funnelId): ?SalesFunnelRecord;
}
