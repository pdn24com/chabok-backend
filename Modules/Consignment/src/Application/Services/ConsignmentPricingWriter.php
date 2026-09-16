<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Services;

use Carbon\CarbonImmutable;

final readonly class ConsignmentPricingWriter
{
    public function __construct(
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Pricing\Application\Contracts\ConsignmentQuoteAcceptance $quoteAcceptance,
        private \Modules\Consignment\Application\Repositories\ConsignmentRepository $consignments,
    )
    {
    }

    public function persistPricing(string $hqId, string $consignmentId, int $version, string $actorId, array $accepted): string
    {
        $id = $this->identifiers->uuid();
        $acceptedAt = $this->clock->now();
        $snapshotId = ($accepted['provider_code'] ?? 'LEGACY_CORE') === 'INTERNAL' ? $this->quoteAcceptance->acceptForConsignment($hqId, $consignmentId, $actorId, $version, $accepted) : null;
        $this->consignments->insertPricingVersion([
            'pricing_version_id' => $id,
            'pricing_snapshot_id' => $snapshotId,
            'service_offering_version_id' => $accepted['service_offering_version_id'] ?? null,
            'hq_id' => $hqId,
            'consignment_id' => $consignmentId,
            'version_number' => $version,
            'provider_code' => $accepted['provider_code'] ?? 'LEGACY_CORE',
            'quote_id' => $accepted['quote_id'],
            'quote_version' => $accepted['quote_version'],
            'option_id' => $accepted['option_id'],
            'external_method_code' => $accepted['external_method_code'],
            'method_name' => $accepted['method_name'],
            'external_price_list_code' => $accepted['external_price_list_code'],
            'zone' => $accepted['zone'],
            'currency' => $accepted['currency'],
            'total_amount' => $accepted['total_amount'],
            'min_ins' => $accepted['min_ins'],
            'delivery_windows' => json_encode($accepted['delivery_windows'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'input_fingerprint' => $accepted['input_fingerprint'],
            'result_fingerprint' => $accepted['result_fingerprint'] ?? null,
            'provider_calculated_at' => CarbonImmutable::parse($accepted['provider_calculated_at']),
            'accepted_at' => $acceptedAt,
            'accepted_by' => $actorId,
        ]);
        foreach ($accepted['charge_lines'] as $index => $line) {
            $this->consignments->insertPricingChargeLine([
                'pricing_charge_line_id' => $this->identifiers->uuid(),
                'hq_id' => $hqId,
                'pricing_version_id' => $id,
                'line_number' => $index + 1,
                'charge_code' => $line['charge_code'],
                'title' => $line['title'],
                'category' => $line['category'] ?? null,
                'calculation_method' => $line['calculation_method'] ?? null,
                'basis' => $line['basis'] ?? null,
                'quantity' => $line['quantity'] ?? null,
                'unit_rate' => $line['unit_rate'] ?? null,
                'amount' => $line['amount'],
                'explanation' => isset($line['explanation']) ? json_encode($line['explanation'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
            ]);
        }
        $this->consignments->acceptPricing($hqId, $consignmentId, [
            'service_offering_id' => $accepted['service_offering_id'] ?? null,
            'service_offering_version_id' => $accepted['service_offering_version_id'] ?? null,
            'catalog_snapshot' => isset($accepted['catalog_snapshot']) ? json_encode($accepted['catalog_snapshot'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
            'commercial_pricing_state' => 'LOCKED',
            'active_pricing_snapshot_id' => $snapshotId,
            'pricing_relevant_fingerprint' => $accepted['input_fingerprint'],
        ]);
        return $id;
    }
}
