<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Pricing;

use Modules\Consignment\Application\Contracts\PricingQuoteProviderInterface;
use Modules\Consignment\Application\Dto\ConsignmentPricingRequestDto;
use Modules\Consignment\Application\Mappers\ConsignmentQuoteMapper;
use Modules\Consignment\Application\Serialization\ConsignmentDraftDocument;
use Modules\Foundation\Domain\Enums\Currency;
use Modules\Pricing\Application\Mappers\QuoteInputMapper;
use Modules\Pricing\Application\UseCases\CalculatePricingQuote\CalculatePricingQuoteCommand;
use Modules\Pricing\Application\UseCases\CalculatePricingQuote\CalculatePricingQuoteHandler;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingQuoteLineRecord;

final readonly class InternalPricingAdapter implements PricingQuoteProviderInterface
{
    public function __construct(private CalculatePricingQuoteHandler $calculatePricingQuoteHandler) {}

    public function calculate(ConsignmentPricingRequestDto $request): array
    {
        $actor = $request->actor;
        $key = 'consignment-'.$request->requestId;
        $quote = $this->calculatePricingQuoteHandler->handle(new CalculatePricingQuoteCommand($actor, QuoteInputMapper::quote(ConsignmentDraftDocument::draft($request->input)), $key));
        $labels = $quote->resolution_evidence['service']['labels'] ?? [];

        return [
            ConsignmentQuoteMapper::option([
                'available' => true,
                'provider_code' => 'INTERNAL',
                'internal_quote_id' => $quote->quote_id,
                'external_method_code' => (string) $quote->service_offering_id,
                'method_name' => (string) ($labels['fa'] ?? $labels['en'] ?? 'Chabok service'),
                'icon' => null,
                'external_price_list_code' => (string) ($quote->resolution_evidence['tariff_code'] ?? ''),
                'zone' => (string) $quote->destination_zone_id,
                'currency' => Currency::Irr->value,
                'total_amount' => (int) $quote->total_amount,
                'billable_weight_kg' => (float) $quote->resolution_evidence['weight']['billable_weight_kg'],
                'unavailable_reason' => null,
                'min_ins' => 0,
                'delivery_windows' => [],
                'charge_lines' => $quote->lines->map(static fn (PricingQuoteLineRecord $line): array => [
                    'charge_code' => $line->charge_type_code,
                    'title' => $line->title,
                    'amount' => (int) $line->amount,
                    'rate_rule_id' => $line->rate_rule_id,
                    'charge_type_id' => $line->charge_type_id,
                    'category' => $line->chargeType->category,
                    'calculation_method' => $line->calculation_method,
                    'basis' => $line->basis,
                    'quantity' => (float) $line->quantity,
                    'unit_rate' => $line->unit_rate === null ? null : (float) $line->unit_rate,
                    'explanation' => $line->explanation,
                ])->all(),
                'service_offering_id' => $quote->service_offering_id,
                'service_offering_version_id' => $quote->service_offering_version_id,
                'service_type_id' => $quote->resolution_evidence['service']['service_type_id'],
                'shipping_method_id' => $quote->resolution_evidence['service']['shipping_method_id'],
                'catalog_snapshot' => $quote->resolution_evidence['service'],
                'commitment' => $quote->resolution_evidence['service']['commitment'] ?? null,
                'selected_option_version_ids' => $quote->resolution_evidence['service']['selected_option_version_ids'] ?? [],
                'result_fingerprint' => $quote->result_fingerprint,
                'warnings' => $quote->warnings,
            ]),
        ];
    }
}
