<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Repositories;

use Modules\Pricing\Application\Repositories\PricingAcceptanceRepository;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingQuoteRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingSnapshotRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingQuoteLineRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingChargeLineRecord;

final class EloquentPricingAcceptanceRepository implements PricingAcceptanceRepository
{
    public function lockQuote(string $hqId, string $quoteId): ?object
    {
        return PricingQuoteRecord::query()->toBase()->where(['hq_id' => $hqId, 'quote_id' => $quoteId])->lockForUpdate()->first();
    }

    public function quoteLines(string $quoteId): array
    {
        return PricingQuoteLineRecord::query()->toBase()->where('quote_id', $quoteId)->orderBy('line_number')->get()->all();
    }

    public function insertSnapshot(array $attributes): void
    {
        PricingSnapshotRecord::query()->toBase()->insert($attributes);
    }

    public function insertChargeLine(array $attributes): void
    {
        PricingChargeLineRecord::query()->toBase()->insert($attributes);
    }

    public function updateQuote(string $quoteId, array $changes): void
    {
        PricingQuoteRecord::query()->toBase()->where('quote_id', $quoteId)->update($changes);
    }
}
