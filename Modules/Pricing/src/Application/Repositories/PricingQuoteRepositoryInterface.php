<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Repositories;

use Modules\Pricing\Infrastructure\Persistence\Models\PricingQuoteRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingSnapshotRecord;

interface PricingQuoteRepositoryInterface
{
    /** The quote a repeated request already produced, so the same idempotency key never prices twice. */
    public function findByIdempotencyKey(?string $hqId, string $requestedBy, string $idempotencyKey): ?PricingQuoteRecord;

    /** @param array<string, mixed> $attributes */
    public function createQuote(array $attributes): string;

    public function lockTenantQuote(?string $hqId, string $quoteId): ?PricingQuoteRecord;

    /** One quote with the charge lines its response renders. */
    public function findTenantQuoteWithLines(?string $hqId, string $quoteId): ?PricingQuoteRecord;

    /** @param array<string, mixed> $changes */
    public function rejectOfferedQuote(?string $hqId, string $quoteId, array $changes): void;

    /** @param list<array<string, mixed>> $rows Attribute sets; the repository applies the model's own casts. */
    public function insertQuoteLines(array $rows): void;

    /** The snapshot a repeated acceptance already produced. */
    public function findSnapshotByIdempotencyKey(?string $hqId, string $acceptedBy, string $idempotencyKey): ?PricingSnapshotRecord;

    /** @param array<string, mixed> $attributes */
    public function createSnapshot(array $attributes): PricingSnapshotRecord;

    public function snapshotWithLines(string $snapshotId): PricingSnapshotRecord;

    /** @param list<array<string, mixed>> $rows */
    public function insertChargeLines(array $rows): void;
}
