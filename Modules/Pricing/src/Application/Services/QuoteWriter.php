<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Domain\Enums\Currency;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Pricing\Application\Contracts\PricingConfigurationWriterInterface;
use Modules\Pricing\Application\Contracts\PricingReaderInterface;
use Modules\Pricing\Application\Contracts\PricingSettingsInterface;
use Modules\Pricing\Application\Contracts\QuoteWriterInterface;
use Modules\Pricing\Application\Dto\CalculatedQuoteDto;
use Modules\Pricing\Application\Repositories\PricingQuoteRepositoryInterface;
use Modules\Pricing\Application\Serialization\QuoteEvidenceDocument;
use Modules\Pricing\Application\Serialization\QuoteInputDocument;
use Modules\Pricing\Domain\Enums\PricingPurpose;
use Modules\Pricing\Domain\Enums\QuoteStatus;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingQuoteRecord;

/** Persists a calculated quote and its charge lines, then reads the stored quote back. */
final readonly class QuoteWriter implements QuoteWriterInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private PricingConfigurationWriterInterface $pricingConfigurationWriter,
        private PricingQuoteRepositoryInterface $pricingQuoteRepository,
        private PricingReaderInterface $pricingReader,
        private PricingSettingsInterface $pricingSettings,
    ) {}

    public function write(AuthenticatedPrincipal $actor, string $idempotencyKey, CalculatedQuoteDto $quote): PricingQuoteRecord
    {
        $input = $quote->input;
        $resolution = $quote->resolution;
        $tariff = $resolution->tariff;
        $offering = $resolution->offering;
        $origin = $resolution->lane->origin->zone;
        $destination = $resolution->lane->destination->zone;
        $resolvedZoneSetVersionId = $resolution->resolvedZoneSetVersionId;
        $inputFingerprint = $quote->inputFingerprint;
        $calculation = $quote->calculation;
        $now = $quote->calculatedAt;
        $warnings = $quote->warnings;
        $evidence = QuoteEvidenceDocument::serialize($resolution);
        $ttl = $this->pricingSettings->quoteTtlSeconds();
        $quoteId = $this->connection->transaction(function () use ($actor, $input, $idempotencyKey, $inputFingerprint, $offering, $tariff, $resolvedZoneSetVersionId, $origin, $destination, $calculation, $now, $ttl, $evidence, $warnings): string {
            $quoteId = $this->pricingQuoteRepository->createQuote([
                'hq_id' => $actor->hqId,
                'requested_by' => $actor->userId,
                'purpose' => PricingPurpose::Sales->value,
                'tariff_version_id' => $tariff->tariff_version_id,
                'zone_set_version_id' => $resolvedZoneSetVersionId,
                'service_offering_id' => $offering->offeringId,
                'service_offering_version_id' => $offering->offeringVersionId,
                'origin_zone_id' => $origin->pricing_zone_id,
                'destination_zone_id' => $destination->pricing_zone_id,
                'currency' => Currency::Irr->value,
                'subtotal_amount' => $calculation->subtotalAmount,
                'discount_amount' => $calculation->discountAmount,
                'tax_amount' => $calculation->taxAmount,
                'total_amount' => $calculation->totalAmount,
                'normalized_input' => QuoteInputDocument::quote($input),
                'resolution_evidence' => $evidence,
                'warnings' => $warnings,
                'input_fingerprint' => $inputFingerprint,
                'result_fingerprint' => $calculation->fingerprint,
                'idempotency_key' => $idempotencyKey,
                'status' => QuoteStatus::Offered->value,
                'calculated_at' => $now,
                'expires_at' => $now->addSeconds($ttl),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->pricingConfigurationWriter->insertLines($quoteId, $calculation->lines);

            return $quoteId;
        }, attempts: 3);

        return $this->pricingReader->quoteDetail($actor, $quoteId);
    }
}
