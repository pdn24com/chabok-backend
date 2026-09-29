<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\AcceptConsignmentPricingQuote;

use Carbon\CarbonImmutable;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Pricing\Application\Repositories\PricingQuoteRepositoryInterface;
use Modules\Pricing\Domain\Enums\QuoteStatus;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingSnapshotRecord;
use Modules\ServiceCatalog\Application\Contracts\QuoteCatalogGuardInterface;
use Modules\ServiceCatalog\Application\Dto\QuoteCatalogReferencesDto;

final readonly class AcceptConsignmentPricingQuoteHandler
{
    public function __construct(
        private QuoteCatalogGuardInterface $quoteCatalogGuard,
        private ClockInterface $clock,
        private PricingQuoteRepositoryInterface $pricingQuoteRepository,
    ) {}

    public function handle(AcceptConsignmentPricingQuoteCommand $command): string
    {
        $hqId = $command->hqId;
        $consignmentId = $command->consignmentId;
        $actorId = $command->actorId;
        $version = $command->version;
        $quoteId = $command->quoteId;
        $quote = $this->pricingQuoteRepository->lockTenantQuote($hqId, $quoteId);
        if ($quote === null || (string) $quote->status !== QuoteStatus::Offered->value) {
            throw new ApiException(ApiErrorCode::PricingQuoteMismatch, 422, 'pricing.internal_pricing_quote_is_unavailable');
        }
        if (CarbonImmutable::parse((string) $quote->expires_at) < $this->clock->now()) {
            throw new ApiException(ApiErrorCode::PricingQuoteExpired, 422, 'pricing.internal_pricing_quote_has_expired');
        }
        $this->quoteCatalogGuard->assertQuoteCurrent(QuoteCatalogReferencesDto::fromEvidence($quote->hq_id, $quote->service_offering_version_id, $quote->resolution_evidence ?? []));
        $now = $this->clock->now();
        $snapshotId = (string) PricingSnapshotRecord::query()->forceCreate([

            'hq_id' => $hqId,
            'quote_id' => $quoteId,
            'object_type' => 'CONSIGNMENT',
            'object_id' => $consignmentId,
            'purpose' => 'SALES',
            'currency' => $quote->currency,
            'subtotal_amount' => $quote->subtotal_amount,
            'discount_amount' => $quote->discount_amount,
            'tax_amount' => $quote->tax_amount,
            'total_amount' => $quote->total_amount,
            'input_fingerprint' => $quote->input_fingerprint,
            'result_fingerprint' => $quote->result_fingerprint,
            'acceptance_idempotency_key' => "consignment:{$consignmentId}:{$version}",
            'accepted_by' => $actorId,
            'accepted_at' => $now,
        ])->getKey();
        $lines = [];
        foreach ($quote->lines as $line) {
            $copy = $line->getAttributes();
            unset($copy['id'], $copy['quote_line_id'], $copy['quote_id']);
            $copy['pricing_snapshot_id'] = $snapshotId;
            $lines[] = $copy;
        }
        foreach (array_chunk($lines, 100) as $chunk) {
            $this->pricingQuoteRepository->insertChargeLines($chunk);
        }
        $quote->forceFill(['status' => QuoteStatus::Accepted->value, 'accepted_at' => $now, 'updated_at' => $now])->save();

        return $snapshotId;
    }
}
