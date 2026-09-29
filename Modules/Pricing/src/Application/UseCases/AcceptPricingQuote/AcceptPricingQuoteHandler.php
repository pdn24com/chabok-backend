<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\AcceptPricingQuote;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Arr;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;
use Modules\Pricing\Application\Contracts\PricingReaderInterface;
use Modules\Pricing\Application\Ports\PricingTargetLookupInterface;
use Modules\Pricing\Application\Repositories\PricingQuoteRepositoryInterface;
use Modules\Pricing\Domain\Enums\QuoteStatus;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingQuoteRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingSnapshotRecord;
use Modules\ServiceCatalog\Application\Contracts\QuoteCatalogGuardInterface;
use Modules\ServiceCatalog\Application\Dto\QuoteCatalogReferencesDto;

final readonly class AcceptPricingQuoteHandler
{
    public function __construct(
        private PricingAccessGuardInterface $pricingAccessGuard,
        private ConnectionInterface $connection,
        private PricingReaderInterface $pricingReader,
        private ClockInterface $clock,
        private PricingTargetLookupInterface $pricingTargetLookup,
        private QuoteCatalogGuardInterface $quoteCatalogGuard,
        private PricingQuoteRepositoryInterface $pricingQuoteRepository,
    ) {}

    public function handle(AcceptPricingQuoteCommand $command): PricingSnapshotRecord
    {
        $actor = $command->actor;
        $quoteId = $command->quoteId;
        $objectType = $command->objectType;
        $objectId = $command->objectId;
        $inputFingerprint = $command->inputFingerprint;
        $idempotencyKey = $command->idempotencyKey;
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.quote.calculate', runtime: true);

        return $this->connection->transaction(function () use ($actor, $quoteId, $objectType, $objectId, $inputFingerprint, $idempotencyKey): PricingSnapshotRecord {
            $existing = $this->pricingQuoteRepository->findSnapshotByIdempotencyKey($actor->hqId, $actor->userId, $idempotencyKey);
            if ($existing !== null) {
                if ((string) $existing->quote_id !== $quoteId || (string) $existing->object_type !== $objectType || (string) $existing->object_id !== $objectId) {
                    throw new ApiException(ApiErrorCode::IdempotencyKeyReused, 409, 'pricing.idempotency_key_already_used_another_acceptance');
                }

                return $this->pricingReader->snapshotDetail((string) $existing->pricing_snapshot_id);
            }
            if ($objectType !== 'CONSIGNMENT' || ! $this->pricingTargetLookup->consignmentExists($actor->hqId, $objectId)) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'pricing.pricing_target_not_found');
            }
            $quote = $this->pricingQuoteRepository->lockTenantQuote($actor->hqId, $quoteId);
            if ($quote === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            if (CarbonImmutable::parse((string) $quote->expires_at)->lessThan($this->clock->now())) {
                throw new ApiException(ApiErrorCode::PricingQuoteExpired, 422, 'consignment.pricing_quote_has_expired');
            }
            if ((string) $quote->input_fingerprint !== $inputFingerprint) {
                throw new ApiException(ApiErrorCode::PricingQuoteMismatch, 422, 'pricing.pricing_relevant_input_changed', details: ['reason_code' => 'PRICING_INPUT_CHANGED']);
            }
            if ((string) $quote->status !== QuoteStatus::Offered->value) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'pricing.pricing_quote_is_no_longer_available');
            }
            $this->quoteCatalogGuard->assertQuoteCurrent(QuoteCatalogReferencesDto::fromEvidence($quote->hq_id, $quote->service_offering_version_id, $quote->resolution_evidence ?? []));
            $now = $this->clock->now();
            $snapshot = $this->pricingQuoteRepository->createSnapshot([

                'hq_id' => $actor->hqId,
                'quote_id' => $quoteId,
                'object_type' => $objectType,
                'object_id' => $objectId,
                'purpose' => $quote->purpose,
                'currency' => $quote->currency,
                'subtotal_amount' => $quote->subtotal_amount,
                'discount_amount' => $quote->discount_amount,
                'tax_amount' => $quote->tax_amount,
                'total_amount' => $quote->total_amount,
                'input_fingerprint' => $quote->input_fingerprint,
                'result_fingerprint' => $quote->result_fingerprint,
                'acceptance_idempotency_key' => $idempotencyKey,
                'accepted_by' => $actor->userId,
                'accepted_at' => $now,
            ]);
            $snapshotId = (string) $snapshot->getKey();
            $quote->load('lines.chargeType');
            $this->assertTotalsReconcile($quote);
            $this->copyChargeLines($quote, $snapshotId);
            $quote->forceFill(['status' => QuoteStatus::Accepted->value, 'accepted_at' => $now, 'updated_at' => $now])->save();

            return $snapshot->load('lines');
        }, attempts: 3);
    }

    private function assertTotalsReconcile(PricingQuoteRecord $quote): void
    {
        $subtotal = 0;
        $discount = 0;
        $tax = 0;
        foreach ($quote->lines as $line) {
            $amount = (int) $line->amount;
            switch ($line->chargeType?->category) {
                case 'BASE':
                case 'SURCHARGE':
                case 'COMMISSION':
                    $subtotal += $amount;
                    break;
                case 'DISCOUNT':
                    $discount += $amount;
                    break;
                case 'TAX':
                    $tax += $amount;
                    break;
            }
        }
        if ((int) $quote->subtotal_amount !== (int) $subtotal || (int) $quote->discount_amount !== (int) $discount || (int) $quote->tax_amount !== (int) $tax || (int) $quote->total_amount !== max(0, (int) $subtotal - (int) $discount + (int) $tax)) {
            throw new ApiException(ApiErrorCode::PricingQuoteMismatch, 422, 'pricing.quote_lines_do_not_reconcile_with_totals');
        }
    }

    private function copyChargeLines(PricingQuoteRecord $quote, string $snapshotId): void
    {
        $chargeLines = [];
        foreach ($quote->lines as $line) {
            $attributes = Arr::except($line->getAttributes(), ['id', 'quote_line_id', 'quote_id']);
            $attributes['pricing_snapshot_id'] = $snapshotId;
            $chargeLines[] = $attributes;
        }
        foreach (array_chunk($chargeLines, 100) as $chunk) {
            $this->pricingQuoteRepository->insertChargeLines($chunk);
        }
    }
}
