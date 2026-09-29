<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Repositories;

use Modules\Pricing\Application\Repositories\PricingQuoteRepositoryInterface;
use Modules\Pricing\Domain\Enums\QuoteStatus;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingChargeLineRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingQuoteLineRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingQuoteRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingSnapshotRecord;

final class EloquentPricingQuoteRepository implements PricingQuoteRepositoryInterface
{
    /** Rows written per statement, so a long quote never builds one oversized query. */
    private const BATCH_SIZE = 100;

    public function findByIdempotencyKey(?string $hqId, string $requestedBy, string $idempotencyKey): ?PricingQuoteRecord
    {
        return PricingQuoteRecord::query()
            ->where(['hq_id' => $hqId, 'requested_by' => $requestedBy, 'idempotency_key' => $idempotencyKey])->first();
    }

    public function createQuote(array $attributes): string
    {
        return (string) PricingQuoteRecord::query()->forceCreate($attributes)->getKey();
    }

    public function lockTenantQuote(?string $hqId, string $quoteId): ?PricingQuoteRecord
    {
        return PricingQuoteRecord::query()->where(['hq_id' => $hqId, 'quote_id' => $quoteId])->lockForUpdate()->first();
    }

    public function findTenantQuoteWithLines(?string $hqId, string $quoteId): ?PricingQuoteRecord
    {
        return PricingQuoteRecord::query()->where(['hq_id' => $hqId, 'quote_id' => $quoteId])
            ->with(['lines' => fn ($lines) => $lines->whereHas('chargeType'), 'lines.chargeType'])->first();
    }

    public function rejectOfferedQuote(?string $hqId, string $quoteId, array $changes): void
    {
        PricingQuoteRecord::query()->where(['hq_id' => $hqId, 'quote_id' => $quoteId, 'status' => QuoteStatus::Offered->value])->update($changes);
    }

    public function insertQuoteLines(array $rows): void
    {
        $attributes = array_map(static fn (array $row): array => (new PricingQuoteLineRecord)->forceFill($row)->getAttributes(), $rows);
        foreach (array_chunk($attributes, self::BATCH_SIZE) as $chunk) {
            PricingQuoteLineRecord::query()->insert($chunk);
        }
    }

    public function findSnapshotByIdempotencyKey(?string $hqId, string $acceptedBy, string $idempotencyKey): ?PricingSnapshotRecord
    {
        return PricingSnapshotRecord::query()
            ->where(['hq_id' => $hqId, 'accepted_by' => $acceptedBy, 'acceptance_idempotency_key' => $idempotencyKey])->first();
    }

    public function createSnapshot(array $attributes): PricingSnapshotRecord
    {
        return PricingSnapshotRecord::query()->forceCreate($attributes);
    }

    public function snapshotWithLines(string $snapshotId): PricingSnapshotRecord
    {
        return PricingSnapshotRecord::query()->where('pricing_snapshot_id', $snapshotId)->with('lines')->firstOrFail();
    }

    public function insertChargeLines(array $rows): void
    {
        foreach (array_chunk($rows, self::BATCH_SIZE) as $chunk) {
            PricingChargeLineRecord::query()->insert($chunk);
        }
    }
}
