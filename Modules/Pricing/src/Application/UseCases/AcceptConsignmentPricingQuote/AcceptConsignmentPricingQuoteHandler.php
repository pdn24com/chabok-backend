<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\AcceptConsignmentPricingQuote;

use Carbon\CarbonImmutable;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Application\Contracts\Clock;
use Modules\Foundation\Application\Contracts\IdentifierGenerator;
use Modules\Pricing\Application\Repositories\PricingAcceptanceRepository;
use Modules\ServiceCatalog\Application\Contracts\QuoteCatalogGuard;

final readonly class AcceptConsignmentPricingQuoteHandler
{
    public function __construct(
        private PricingAcceptanceRepository $quotes,
        private QuoteCatalogGuard $catalog,
        private Clock $clock,
        private IdentifierGenerator $identifiers,
    )
    {
    }

    public function handle(AcceptConsignmentPricingQuoteCommand $command): AcceptConsignmentPricingQuoteResult
    {
        return new AcceptConsignmentPricingQuoteResult($this->execute($command->hqId, $command->consignmentId, $command->actorId, $command->version, $command->accepted));
    }

    private function execute(string $hqId, string $consignmentId, string $actorId, int $version, array $accepted): string
    {
        $quoteId = (string) $accepted['internal_quote_id'];
        $quote = $this->quotes->lockQuote($hqId, $quoteId);
        if ($quote === null || (string) $quote->status !== 'OFFERED') {
            throw new ApiException(ApiErrorCode::PricingQuoteMismatch, 422, 'The internal pricing quote is unavailable.');
        }
        if (CarbonImmutable::parse((string) $quote->expires_at) < $this->clock->now()) {
            throw new ApiException(ApiErrorCode::PricingQuoteExpired, 422, 'The internal pricing quote has expired.');
        }
        $this->catalog->assertQuoteCurrent($quote);
        $snapshotId = $this->identifiers->uuid();
        $now = $this->clock->now();
        $this->quotes->insertSnapshot([
            'pricing_snapshot_id' => $snapshotId,
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
        ]);
        foreach ($this->quotes->quoteLines($quoteId) as $line) {
            $copy = (array) $line;
            unset($copy['quote_line_id'], $copy['quote_id']);
            $copy['charge_line_id'] = $this->identifiers->uuid();
            $copy['pricing_snapshot_id'] = $snapshotId;
            $this->quotes->insertChargeLine($copy);
        }
        $this->quotes->updateQuote($quoteId, ['status' => 'ACCEPTED', 'accepted_at' => $now, 'updated_at' => $now]);
        return $snapshotId;
    }
}
