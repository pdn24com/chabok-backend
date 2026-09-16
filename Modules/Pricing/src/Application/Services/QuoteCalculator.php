<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class QuoteCalculator
{
    public function __construct(
        private \Modules\Pricing\Application\Services\PricingAccessGuard $pricingAccessGuard,
        private \Modules\Pricing\Application\Services\PricingInput $pricingInput,
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
        private \Modules\Pricing\Application\Services\PricingReader $pricingReader,
        private \Modules\ServiceCatalog\Application\Contracts\ServiceEligibilityResolver $catalog,
        private \Modules\ServiceCatalog\Application\Contracts\CatalogResolver $currentCatalog,
        private \Modules\Pricing\Application\Services\PricingZoneResolver $pricingZoneResolver,
        private \Modules\Pricing\Domain\PricingFacts $pricingFacts,
        private \Modules\Pricing\Domain\FreightMatrices $matrices,
        private \Modules\Pricing\Application\Services\PricingMatrixMatcher $pricingMatrixMatcher,
        private \Modules\Pricing\Application\ServiceTariffDependencies $serviceTariffs,
        private \Modules\Pricing\Domain\DeterministicCalculator $calculator,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Pricing\Application\Contracts\PricingSettings $settings,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Pricing\Application\Services\PricingConfigurationWriter $pricingConfigurationWriter,
    )
    {
    }

    public function calculate(AuthenticatedPrincipal $actor, array $input, string $idempotencyKey, ?object $draft = null): array
    {
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.quote.calculate', runtime: true);
        $purpose = (string) ($input['purpose'] ?? 'SALES');
        if ($purpose !== 'SALES') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'This pricing purpose is not active in Milestone 1.', details: ['reason_code' => 'PRICING_PURPOSE_NOT_ACTIVE']);
        }
        $input = $this->pricingInput->normalize($input);
        $inputFingerprint = $this->pricingInput->fingerprint($input);
        $existing = $draft === null ? $this->pricing->quoteForRequest($actor->hqId, $actor->userId, $idempotencyKey) : null;
        if ($existing !== null) {
            if ((string) $existing->input_fingerprint !== $inputFingerprint) {
                throw new ApiException(ApiErrorCode::IdempotencyKeyReused, 409, 'The idempotency key was already used with different pricing input.');
            }
            return $this->pricingReader->quoteDetail($actor, (string) $existing->quote_id);
        }
        $offering = $this->catalog->validateSelection($actor, (string) $input['service_offering_id'], $input['service_offering_version_id'] ?? null, $input);
        $offeringReferences = $this->currentCatalog->relatedVersions('offerings', $offering['service_offering_id']);
        $optionReferences = [];
        foreach ($input['selected_option_version_ids'] as $id) {
            $optionReferences = [...$optionReferences, ...$this->currentCatalog->relatedVersions('options', $id)];
        }
        $asOf = CarbonImmutable::parse((string) $input['as_of_timestamp'])->utc();
        $tariff = $draft ?? $this->pricing->eligibleTariff($actor->hqId, $offeringReferences, $asOf);
        if ($tariff === null) {
            throw new ApiException(ApiErrorCode::PricingTariffNotFound, 422, 'No eligible tariff was found.', details: ['reason_code' => 'PRICING_TARIFF_NOT_FOUND']);
        }
        $resolvedZoneSetVersionId = $this->pricingZoneResolver->resolveEffectiveZoneSetVersion((string) $tariff->zone_set_version_id, $asOf);
        [$origin, $originEvidence] = $this->pricingZoneResolver->resolveZone($resolvedZoneSetVersionId, (array) $input['sender'], 'sender');
        [$destination, $destinationEvidence] = $this->pricingZoneResolver->resolveZone($resolvedZoneSetVersionId, (array) $input['receiver'], 'receiver');
        $facts = $this->pricingFacts->facts($input, (array) $tariff, $destination);
        $basisZone = $destination;
        if ($tariff->zone_policy === 'HIGHER_ZONE_RANK') {
            $rankedZones = array_map(fn($z) => (array) $z, $this->pricing->zones($resolvedZoneSetVersionId));
            if (!$this->matrices->ranksValid($rankedZones)) {
                throw new ApiException(ApiErrorCode::PricingZoneUnresolved, 422, 'Zone ranks are incomplete or ambiguous.', details: ['reason_code' => 'PRICING_ZONE_RANK_INCOMPLETE']);
            }
            $basisZone = $origin['rank'] > $destination['rank'] ? $origin : $destination;
        }
        $matrixCell = $this->pricingMatrixMatcher->matrixCell($tariff, $offering, $input, $origin, $basisZone, $facts['billable_weight_kg']);
        $rules = array_map(fn($r) => (array) $r, array_values(array_filter($this->pricing->freightRules($tariff->tariff_version_id, $offeringReferences, $optionReferences), function ($r) use ($matrixCell, $origin, $destination): bool {
            if ($r->matrix_cell_id !== null) {
                return $r->matrix_cell_id === $matrixCell;
            }
            return ($r->origin_code === null || $r->origin_code === $origin['code']) && ($r->destination_code === null || $r->destination_code === $destination['code']);
        })));
        if ($rules === []) {
            throw new ApiException(ApiErrorCode::PricingRuleNotFound, 422, 'No pricing rule matches the selected service and lane.', details: ['reason_code' => 'PRICING_RULE_NOT_FOUND']);
        }
        $dependencyEvidence = [];
        $dependencies = $this->serviceTariffs->resolve($this->serviceTariffs->ids($tariff->tariff_version_id), $actor->hqId, $tariff->zone_set_version_id, $asOf, $rules);
        foreach ($dependencies as $service) {
            $chargeKey = $this->serviceTariffs->chargeKey($service->charge_code);
            $active = !($chargeKey === 'INSURANCE' && !$facts['insurance_enabled'] || $chargeKey === 'COD_FEE' && !$facts['cod_enabled']);
            $dependencyEvidence[] = [
                'tariff_family_id' => $service->tariff_family_id,
                'tariff_version_id' => $service->tariff_version_id,
                'version_number' => $service->version_number,
                'charge_code' => $service->charge_code,
                'applied' => $active,
                'zone_set_version_id' => $service->zone_set_version_id ? $resolvedZoneSetVersionId : null,
            ];
            if (!$active) {
                continue;
            }
            $quantity = (float) match ($service->matrix_basis) {
                'DECLARED_VALUE' => $facts['declared_value_amount'],
                'COD_AMOUNT' => $facts['cod_amount'],
                'ACTUAL_WEIGHT' => $facts['actual_weight_kg'],
                'PARCEL_COUNT' => $facts['parcel_count'],
                default => $facts['billable_weight_kg'],
            };
            $serviceBasis = $destination;
            if (!$service->zone_set_version_id) {
                $serviceBasis = ['code' => 'GLOBAL'];
            } elseif ($service->zone_policy === 'HIGHER_ZONE_RANK') {
                $ranked = array_map(fn($z) => (array) $z, $this->pricing->zones($resolvedZoneSetVersionId));
                if (!$this->matrices->ranksValid($ranked)) {
                    throw new ApiException(ApiErrorCode::PricingZoneUnresolved, 422, 'رتبهٔ زون خدمات کامل نیست.');
                }
                $serviceBasis = $origin['rank'] > $destination['rank'] ? $origin : $destination;
            }
            $serviceCell = $this->pricingMatrixMatcher->matrixCell($service, $offering, $input, $origin, $serviceBasis, $quantity);
            $serviceRules = $this->pricing->serviceRules($service->tariff_version_id, $serviceCell);
            if (count($serviceRules) !== 1) {
                throw new ApiException(ApiErrorCode::PricingRuleNotFound, 422, 'نرخ خدمات برای این بازه تعریف نشده است.');
            }
            foreach ($serviceRules as $rule) {
                $rules[] = [...(array) $rule, 'service_tariff_version_id' => $service->tariff_version_id];
            }
        }
        $calculation = $this->calculator->calculate($rules, $facts);
        if ($calculation['lines'] === [] || $calculation['total_amount'] <= 0 || !array_filter($calculation['lines'], fn($line) => $line['charge_code'] === 'BASE_FREIGHT')) {
            throw new ApiException(ApiErrorCode::PricingRejected, 422, 'Pricing did not produce a complete nonzero base price.', details: ['reason_code' => 'PRICING_INCOMPLETE_RESULT']);
        }
        if (($input['insurance_enabled'] ?? false) === true && !array_filter($calculation['lines'], fn($line) => in_array($line['charge_code'], ['INSURANCE', 'INSURANCE_FEE'], true))) {
            throw new ApiException(ApiErrorCode::PricingRejected, 422, 'Mandatory insurance pricing is unavailable.', details: ['reason_code' => 'INSURANCE_PRICING_REQUIRED']);
        }
        $quoteId = $this->identifiers->uuid();
        $now = CarbonImmutable::instance($this->clock->now())->utc();
        $ttl = (int) $this->settings->quoteTtlSeconds();
        $evidence = [
            'tariff_code' => $tariff->tariff_code,
            'zone_set' => [
                'configured_version_id' => (string) $tariff->zone_set_version_id,
                'resolved_version_id' => $resolvedZoneSetVersionId,
            ],
            'origin' => $originEvidence,
            'destination' => $destinationEvidence,
            'weight' => $facts,
            'service' => [
                'outcome' => $offering['outcome'],
                'reason_codes' => $offering['reason_codes'],
                'labels' => $offering['labels'] ?? [],
                'service_type_labels' => $offering['service_type_labels'] ?? [],
                'shipping_method_labels' => $offering['shipping_method_labels'] ?? [],
                'service_type_id' => $offering['service_type_id'],
                'shipping_method_id' => $offering['shipping_method_id'],
                'selected_option_version_ids' => array_map(fn($id) => $this->currentCatalog->resolve('options', $id, (string) $actor->hqId)['service_option_version_id'], $input['selected_option_version_ids']),
                'selected_services' => array_values(array_filter($offering['options'], fn($option) => count(array_intersect($this->currentCatalog->relatedVersions('options', $option['service_option_id']), $input['selected_option_version_ids'])) > 0)),
                'service_offering_version_id' => $offering['service_offering_version_id'],
                'service_type_version_id' => $offering['service_type_version_id'],
                'shipping_method_version_id' => $offering['shipping_method_version_id'],
                'commitment' => $offering['commitment'] ?? null,
            ],
        ];
        $warnings = $facts['weight_evidence'] === 'AGGREGATE_FALLBACK' ? ['PRICING_AGGREGATE_WEIGHT_FALLBACK'] : [];
        $evidence['tariff_title'] = $tariff->tariff_title;
        $evidence['tariff_version_number'] = (int) $tariff->version_number;
        $evidence['zone_policy'] = $tariff->zone_policy;
        $evidence['zones'] = ['origin' => $origin, 'destination' => $destination, 'basis' => $basisZone];
        $evidence['matrix_cell_id'] = $matrixCell;
        $evidence['service_tariffs'] = $dependencyEvidence;
        $evidence['selection'] = (bool) ($tariff->is_default ?? false) ? 'SERVICE_DEFAULT' : 'LEGACY_PRIORITY';
        if ($draft !== null) {
            return [
                ...$calculation,
                'mode' => 'DRAFT',
                'acceptable' => false,
                'tariff_version_id' => $tariff->tariff_version_id,
                'lock_version' => (int) $tariff->lock_version,
                'zone_set_version_id' => $resolvedZoneSetVersionId,
                'currency' => 'IRR',
                'calculated_at' => $now->toISOString(),
                'resolution_evidence' => $evidence,
                'warnings' => $warnings,
                'lines' => array_map(fn($l) => [...$l, 'charge_type_code' => $l['charge_code']], $calculation['lines']),
            ];
        }
        $this->transactions->run(function () use ($actor, $input, $idempotencyKey, $inputFingerprint, $offering, $tariff, $resolvedZoneSetVersionId, $origin, $destination, $calculation, $quoteId, $now, $ttl, $evidence, $warnings): void {
            $this->pricing->insertQuote([
                'quote_id' => $quoteId,
                'hq_id' => $actor->hqId,
                'requested_by' => $actor->userId,
                'purpose' => 'SALES',
                'tariff_version_id' => $tariff->tariff_version_id,
                'zone_set_version_id' => $resolvedZoneSetVersionId,
                'service_offering_id' => $offering['service_offering_id'],
                'service_offering_version_id' => $offering['service_offering_version_id'],
                'origin_zone_id' => $origin['pricing_zone_id'],
                'destination_zone_id' => $destination['pricing_zone_id'],
                'currency' => 'IRR',
                'subtotal_amount' => $calculation['subtotal_amount'],
                'discount_amount' => $calculation['discount_amount'],
                'tax_amount' => $calculation['tax_amount'],
                'total_amount' => $calculation['total_amount'],
                'normalized_input' => json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'resolution_evidence' => json_encode($evidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'warnings' => json_encode($warnings, JSON_THROW_ON_ERROR),
                'input_fingerprint' => $inputFingerprint,
                'result_fingerprint' => $calculation['result_fingerprint'],
                'idempotency_key' => $idempotencyKey,
                'status' => 'OFFERED',
                'calculated_at' => $now,
                'expires_at' => $now->addSeconds($ttl),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->pricingConfigurationWriter->insertLines($quoteId, $calculation['lines']);
        });
        return $this->pricingReader->quoteDetail($actor, $quoteId);
    }
}
