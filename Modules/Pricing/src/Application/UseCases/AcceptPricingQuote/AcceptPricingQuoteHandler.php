<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\AcceptPricingQuote;

use Carbon\CarbonImmutable;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class AcceptPricingQuoteHandler
{
    public function __construct(
        private \Modules\Pricing\Application\Services\PricingAccessGuard $pricingAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
        private \Modules\Pricing\Application\Services\PricingReader $pricingReader,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\ServiceCatalog\Application\Contracts\CatalogResolver $currentCatalog,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
    )
    {
    }

    public function handle(AcceptPricingQuoteCommand $command): AcceptPricingQuoteResult
    {
        return new AcceptPricingQuoteResult($this->execute($command->actor, $command->quoteId, $command->objectType, $command->objectId, $command->inputFingerprint, $command->idempotencyKey));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $quoteId,
        string $objectType,
        string $objectId,
        string $inputFingerprint,
        string $idempotencyKey,
    ): array
    {
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.quote.calculate', runtime: true);
        return $this->transactions->run(function () use ($actor, $quoteId, $objectType, $objectId, $inputFingerprint, $idempotencyKey): array {
            $existing = $this->pricing->snapshotForAcceptance($actor->hqId, $actor->userId, $idempotencyKey);
            if ($existing !== null) {
                if ((string) $existing->quote_id !== $quoteId || (string) $existing->object_type !== $objectType || (string) $existing->object_id !== $objectId) {
                    throw new ApiException(ApiErrorCode::IdempotencyKeyReused, 409, 'The idempotency key was already used for another acceptance.');
                }
                return $this->pricingReader->snapshotDetail((string) $existing->pricing_snapshot_id);
            }
            if ($objectType !== 'CONSIGNMENT' || !$this->pricing->consignmentExists($actor->hqId, $objectId)) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Pricing target not found.');
            }
            $quote = $this->pricing->lockQuote($actor->hqId, $quoteId);
            if ($quote === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            if (CarbonImmutable::parse((string) $quote->expires_at)->lessThan($this->clock->now())) {
                throw new ApiException(ApiErrorCode::PricingQuoteExpired, 422, 'The pricing quote has expired.');
            }
            if ((string) $quote->input_fingerprint !== $inputFingerprint) {
                throw new ApiException(ApiErrorCode::PricingQuoteMismatch, 422, 'Pricing-relevant input changed.', details: ['reason_code' => 'PRICING_INPUT_CHANGED']);
            }
            if ((string) $quote->status !== 'OFFERED') {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'The pricing quote is no longer available.');
            }
            $this->currentCatalog->assertQuoteCurrent($quote);
            $snapshotId = $this->identifiers->uuid();
            $now = $this->clock->now();
            $this->pricing->insertSnapshot([
                'pricing_snapshot_id' => $snapshotId,
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
            $lines = array_map(fn($r) => (array) $r, $this->pricing->quoteLines($quoteId));
            $categories = $this->pricing->chargeCategories(array_column($lines, 'charge_type_id'));
            $subtotal = array_sum(array_column(array_filter($lines, fn($line) => in_array($categories[$line['charge_type_id']] ?? null, ['BASE', 'SURCHARGE', 'COMMISSION'], true)), 'amount'));
            $discount = array_sum(array_column(array_filter($lines, fn($line) => ($categories[$line['charge_type_id']] ?? null) === 'DISCOUNT'), 'amount'));
            $tax = array_sum(array_column(array_filter($lines, fn($line) => ($categories[$line['charge_type_id']] ?? null) === 'TAX'), 'amount'));
            if ((int) $quote->subtotal_amount !== (int) $subtotal || (int) $quote->discount_amount !== (int) $discount || (int) $quote->tax_amount !== (int) $tax || (int) $quote->total_amount !== max(0, (int) $subtotal - (int) $discount + (int) $tax)) {
                throw new ApiException(ApiErrorCode::PricingQuoteMismatch, 422, 'Quote lines do not reconcile with totals.');
            }
            foreach ($lines as $line) {
                unset($line['quote_line_id'], $line['quote_id']);
                $line['charge_line_id'] = $this->identifiers->uuid();
                $line['pricing_snapshot_id'] = $snapshotId;
                $this->pricing->insertChargeLine($line);
            }
            $this->pricing->acceptQuote($quoteId, $now);
            return $this->pricingReader->snapshotDetail($snapshotId);
        });
    }
}
