<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Pricing;

use Modules\Consignment\Application\Contracts\PricingQuoteProvider;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Pricing\Application\PricingService;

final readonly class InternalPricingAdapter implements PricingQuoteProvider
{
    public function __construct(private PricingService $pricing) {}

    public function calculate(array $normalizedInput): array
    {
        $actor = new AuthenticatedPrincipal((string) $normalizedInput['_actor_user_id'], (string) $normalizedInput['_actor_session_id'], (string) $normalizedInput['_hq_id'], false);
        $key = 'consignment-'.(string) $normalizedInput['_pricing_request_id'];
        $quote = $this->pricing->calculateQuote($actor, $normalizedInput, $key);
        $labels = $quote['resolution_evidence']['service']['labels'] ?? [];
        return [[
            'available' => true,
            'provider_code' => 'INTERNAL',
            'internal_quote_id' => $quote['quote_id'],
            'external_method_code' => (string) $quote['service_offering_id'],
            'method_name' => (string) ($labels['fa'] ?? $labels['en'] ?? 'Chabok service'),
            'icon' => null,
            'external_price_list_code' => (string) ($quote['resolution_evidence']['tariff_code'] ?? ''),
            'zone' => (string) $quote['destination_zone_id'],
            'currency' => 'IRR',
            'total_amount' => (int) $quote['total_amount'],
            'billable_weight_kg' => (float) $quote['resolution_evidence']['weight']['billable_weight_kg'],
            'unavailable_reason' => null,
            'min_ins' => 0,
            'delivery_windows' => [],
            'charge_lines' => array_map(static fn (array $line): array => [
                'charge_code' => $line['charge_type_code'], 'title' => $line['title'], 'amount' => (int) $line['amount'],
                'rate_rule_id' => $line['rate_rule_id'], 'charge_type_id' => $line['charge_type_id'],
                'category' => $line['category'], 'calculation_method' => $line['calculation_method'],
                'basis' => $line['basis'], 'quantity' => (float) $line['quantity'],
                'unit_rate' => $line['unit_rate'] === null ? null : (float) $line['unit_rate'],
                'explanation' => $line['explanation'],
            ], $quote['lines']),
            'service_offering_id' => $quote['service_offering_id'],
            'service_offering_version_id' => $quote['service_offering_version_id'],
            'service_type_id' => $quote['resolution_evidence']['service']['service_type_id'],
            'shipping_method_id' => $quote['resolution_evidence']['service']['shipping_method_id'],
            'catalog_snapshot' => $quote['resolution_evidence']['service'],
            'commitment' => $quote['resolution_evidence']['service']['commitment'] ?? null,
            'selected_option_version_ids' => $quote['resolution_evidence']['service']['selected_option_version_ids'] ?? [],
            'result_fingerprint' => $quote['result_fingerprint'],
            'warnings' => $quote['warnings'],
        ]];
    }
}
