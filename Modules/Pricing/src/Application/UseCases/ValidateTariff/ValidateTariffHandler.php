<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ValidateTariff;

use Carbon\CarbonImmutable;
use Modules\Pricing\Application\TariffMatrixCompiler;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ValidateTariffHandler
{
    public function __construct(
        private \Modules\Pricing\Application\Services\PricingAccessGuard $pricingAccessGuard,
        private \Modules\Pricing\Application\Services\PricingReader $pricingReader,
        private \Modules\Pricing\Domain\FreightMatrices $matrices,
        private \Modules\Pricing\Application\Services\PricingZoneResolver $pricingZoneResolver,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Pricing\Application\Services\PricingVersionGuard $pricingVersionGuard,
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
        private \Modules\ServiceCatalog\Application\Contracts\CatalogResolver $currentCatalog,
        private \Modules\Pricing\Domain\PricingRulePolicy $pricingRulePolicy,
        private \Modules\Pricing\Application\ServiceTariffDependencies $serviceTariffs,
    )
    {
    }

    public function handle(ValidateTariffCommand $command): ValidateTariffResult
    {
        return new ValidateTariffResult($this->execute($command->actor, $command->versionId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $versionId): array
    {
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.manage_draft');
        $version = $this->pricingReader->tariffVersion($actor, $versionId);
        $zones = $version['zone_set_version_id'] ? $this->pricingReader->zoneVersion($actor, $version['zone_set_version_id'])['zones'] : [['pricing_zone_id' => TariffMatrixCompiler::GLOBAL_COLUMN]];
        $errors = $this->matrices->validate($version['freight_matrices'] ?? [], array_column($zones, 'pricing_zone_id'), $version['zone_policy'], true);
        if ($version['zone_set_version_id']) {
            try {
                $effectiveZoneVersionId = $this->pricingZoneResolver->resolveEffectiveZoneSetVersion($version['zone_set_version_id'], CarbonImmutable::instance($this->clock->now()));
                $effectiveZones = $this->pricingReader->zoneVersion($actor, $effectiveZoneVersionId)['zones'];
                if ($version['zone_policy'] === 'HIGHER_ZONE_RANK' && !$this->matrices->ranksValid($effectiveZones)) {
                    $errors[] = ['code' => 'PRICING_ZONE_RANK_INCOMPLETE', 'field' => 'zone_set_version_id'];
                }
            } catch (ApiException $exception) {
                if (!in_array($exception->errorCode, [ApiErrorCode::PricingZoneUnresolved, ApiErrorCode::PricingZoneAmbiguous], true)) {
                    throw $exception;
                }
                $errors[] = ['code' => 'PRICING_ZONE_VERSION_NOT_PUBLISHED', 'field' => 'zone_set_version_id'];
            }
        }
        if (!$version['valid_from']) {
            $errors[] = ['code' => 'PRICING_VALID_FROM_REQUIRED', 'field' => 'valid_from'];
        }
        if ($version['valid_from'] && $version['valid_to'] && $version['valid_to'] <= $version['valid_from']) {
            $errors[] = ['code' => 'PRICING_EFFECTIVE_INTERVAL_INVALID', 'field' => 'valid_to'];
        }
        if ($this->pricingVersionGuard->hasVersionOverlap('tariffs', $version)) {
            $errors[] = ['code' => 'PRICING_EFFECTIVE_INTERVAL_OVERLAP', 'field' => 'valid_from'];
        }
        if ($version['rules'] === []) {
            $errors[] = ['code' => 'PRICING_RULE_NOT_FOUND', 'field' => 'rules'];
        }
        foreach ($version['rules'] as $rule) {
            if ($version['tariff_kind'] === 'FREIGHT' && !$this->pricing->offeringHasPublishedSuccessor($rule['service_offering_version_id'])) {
                $errors[] = ['code' => 'PRICING_SERVICE_VERSION_NOT_PUBLISHED', 'field' => 'rules'];
            }
            if ($rule['service_option_version_id'] !== null && !$this->currentCatalog->optionBound($rule['service_offering_version_id'], $rule['service_option_version_id'], (string) $actor->hqId)) {
                $errors[] = ['code' => 'PRICING_SERVICE_OPTION_NOT_BOUND', 'field' => 'rules'];
            }
            if ($rule['range_from'] !== null && $rule['range_to'] !== null && (float) $rule['range_from'] >= (float) $rule['range_to']) {
                $errors[] = ['code' => 'PRICING_RANGE_INVALID', 'field' => 'rules'];
            }
            $method = (string) $rule['calculation_method'];
            if ($method === 'FIXED' && $rule['fixed_amount'] === null) {
                $errors[] = ['code' => 'PRICING_FIXED_AMOUNT_REQUIRED', 'field' => 'rules'];
            }
            if (in_array($method, ['PER_UNIT', 'TIERED'], true) && $rule['unit_rate'] === null) {
                $errors[] = ['code' => 'PRICING_UNIT_RATE_REQUIRED', 'field' => 'rules'];
            }
            if ($method === 'SLAB' && $rule['fixed_amount'] === null && $rule['unit_rate'] === null) {
                $errors[] = ['code' => 'PRICING_SLAB_RATE_REQUIRED', 'field' => 'rules'];
            }
            if ($method === 'PERCENT' && $rule['percentage_bps'] === null) {
                $errors[] = ['code' => 'PRICING_PERCENTAGE_REQUIRED', 'field' => 'rules'];
            }
            if ($method === 'MIN_MAX' && $rule['minimum_amount'] === null && $rule['maximum_amount'] === null) {
                $errors[] = ['code' => 'PRICING_MIN_MAX_BOUND_REQUIRED', 'field' => 'rules'];
            }
            if ($rule['minimum_amount'] !== null && $rule['maximum_amount'] !== null && $rule['minimum_amount'] > $rule['maximum_amount']) {
                $errors[] = ['code' => 'PRICING_MIN_MAX_INVALID', 'field' => 'rules'];
            }
            if (($rule['amount_rounding_mode'] ?? 'NONE') !== 'NONE' && empty($rule['amount_rounding_step'])) {
                $errors[] = ['code' => 'PRICING_AMOUNT_ROUNDING_STEP_REQUIRED', 'field' => 'rules'];
            }
        }
        $ruleCounts = [];
        foreach ($version['rules'] as $r) {
            $key = implode('|', [
                $r['service_offering_version_id'],
                $r['service_option_version_id'],
                $r['origin_zone_id'],
                $r['destination_zone_id'],
                $r['charge_type_id'],
                $r['priority'],
                $r['range_from'],
                $r['range_to'],
            ]);
            $ruleCounts[$key] = ($ruleCounts[$key] ?? 0) + 1;
        }
        if (array_filter($ruleCounts, fn($count) => $count > 1)) {
            $errors[] = ['code' => 'PRICING_RULE_AMBIGUOUS', 'field' => 'rules'];
        }
        if ($this->pricingRulePolicy->hasAmbiguousRuleRanges($version['rules'])) {
            $errors[] = ['code' => 'PRICING_RULE_RANGE_OVERLAP', 'field' => 'rules'];
        }
        try {
            $this->serviceTariffs->resolve($version['service_tariff_family_ids'], $actor->hqId, $version['zone_set_version_id'], CarbonImmutable::parse($version['valid_from'] ?? 'now')->max(CarbonImmutable::instance($this->clock->now())), $version['rules']);
        } catch (ApiException $e) {
            $errors[] = [
                'code' => 'PRICING_SERVICE_DEPENDENCY_INVALID',
                'field' => 'service_tariff_family_ids',
                'message' => $e->getMessage(),
            ];
        }
        if ($version['is_default'] && $this->pricingVersionGuard->defaultConflict($version, $actor->hqId)) {
            $errors[] = [
                'code' => 'PRICING_DEFAULT_CONFLICT',
                'field' => 'is_default',
                'message' => 'برای یکی از سرویس‌ها در این بازه، تعرفهٔ پیش‌فرض دیگری منتشر شده است.',
            ];
        }
        try {
            $this->serviceTariffs->assertCompatibleSuccessor($version);
        } catch (ApiException $e) {
            $errors[] = [
                'code' => 'PRICING_SERVICE_DEPENDENCY_INVALID',
                'field' => 'zone_set_version_id',
                'message' => $e->getMessage(),
            ];
        }
        return ['valid' => $errors === [], 'errors' => $errors];
    }
}
