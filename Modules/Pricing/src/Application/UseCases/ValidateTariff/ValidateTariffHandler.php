<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ValidateTariff;

use Carbon\CarbonImmutable;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Support\ApiMessage;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;
use Modules\Pricing\Application\Contracts\PricingVersionGuardInterface;
use Modules\Pricing\Application\Contracts\PricingZoneResolverInterface;
use Modules\Pricing\Application\Contracts\ServiceTariffDependenciesInterface;
use Modules\Pricing\Application\Dto\PricingVersionPeriodDto;
use Modules\Pricing\Application\Mappers\FreightMatrixInput;
use Modules\Pricing\Application\Mappers\TariffRuleInput;
use Modules\Pricing\Application\Repositories\PricingZoneRepositoryInterface;
use Modules\Pricing\Application\Repositories\TariffRepositoryInterface;
use Modules\Pricing\Application\Services\TariffMatrixCompiler;
use Modules\Pricing\Domain\Enums\PricingResource;
use Modules\Pricing\Domain\Enums\PricingValidationCode;
use Modules\Pricing\Domain\Enums\ZonePolicy;
use Modules\Pricing\Domain\Policies\ZoneRankPolicy;
use Modules\Pricing\Domain\Validators\FreightMatrixValidator;
use Modules\Pricing\Domain\Validators\TariffRuleValidator;
use Modules\Pricing\Domain\ValueObjects\PricingValidationIssue;
use Modules\Pricing\Domain\ValueObjects\PricingValidationResult;
use Modules\Pricing\Domain\ValueObjects\TariffRuleDefinition;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetVersionRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;
use Modules\ServiceCatalog\Application\Contracts\CatalogSelectionInspectorInterface;
use Modules\ServiceCatalog\Application\Dto\CatalogSelectionDto;

final readonly class ValidateTariffHandler
{
    public function __construct(
        private PricingAccessGuardInterface $pricingAccessGuard,
        private FreightMatrixValidator $freightMatrixValidator,
        private TariffRuleValidator $tariffRuleValidator,
        private ZoneRankPolicy $zoneRankPolicy,
        private PricingZoneResolverInterface $pricingZoneResolver,
        private ClockInterface $clock,
        private PricingVersionGuardInterface $pricingVersionGuard,
        private CatalogSelectionInspectorInterface $catalogSelectionInspector,
        private ServiceTariffDependenciesInterface $serviceTariffDependencies,
        private TariffRepositoryInterface $tariffRepository,
        private PricingZoneRepositoryInterface $pricingZoneRepository,
    ) {}

    public function handle(ValidateTariffCommand $command): PricingValidationResult
    {
        $this->pricingAccessGuard->assertAccess($command->actor, 'pricing.tariff.manage_draft');
        $hqId = $command->actor->hqId;
        $version = $this->tariffRepository->findVisibleVersionDetail($hqId, $command->versionId);
        if ($version === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }
        $rules = [];
        foreach ($version->rules as $record) {
            $rules[] = TariffRuleInput::fromRecord($record);
        }
        $errors = [
            ...$this->matrixIssues($version, $hqId),
            ...$this->periodIssues($version),
            ...$this->ruleIssues($version, $hqId, $rules),
            ...$this->dependencyIssues($version, $hqId, $rules),
        ];
        if ($version->is_default && $this->pricingVersionGuard->defaultConflict($version, $hqId)) {
            $errors[] = new PricingValidationIssue(PricingValidationCode::DEFAULT_CONFLICT, 'is_default', 'برای یکی از سرویس‌ها در این بازه، تعرفهٔ پیش‌فرض دیگری منتشر شده است.');
        }
        $successor = $this->serviceTariffDependencies->successorFailure($version->family->tariff_kind, $version->tariff_family_id, $version->zone_set_version_id);
        if ($successor !== null) {
            $errors[] = new PricingValidationIssue(PricingValidationCode::SERVICE_DEPENDENCY_INVALID, 'zone_set_version_id', ApiMessage::translate($successor->messageKey()));
        }

        return new PricingValidationResult($errors);
    }

    /** @return list<PricingValidationIssue> */
    private function matrixIssues(TariffVersionRecord $version, string $hqId): array
    {
        $configured = $version->zone_set_version_id === null ? null : $this->zoneVersion($version->zone_set_version_id, $hqId);
        $zoneIds = $configured === null ? [TariffMatrixCompiler::GLOBAL_COLUMN] : $configured->zones->pluck('pricing_zone_id')->all();
        $errors = [];
        foreach ($this->freightMatrixValidator->validate(FreightMatrixInput::many($version->freight_matrices ?? []), $zoneIds, ZonePolicy::tryFrom($version->zone_policy), true) as $issue) {
            $errors[] = new PricingValidationIssue($issue->code, $issue->field);
        }
        if ($configured === null) {
            return $errors;
        }
        $effectiveId = $this->pricingZoneResolver->findEffectiveZoneSetVersion($version->zone_set_version_id, CarbonImmutable::instance($this->clock->now()));
        if ($effectiveId === null) {
            $errors[] = new PricingValidationIssue(PricingValidationCode::ZONE_VERSION_NOT_PUBLISHED, 'zone_set_version_id');

            return $errors;
        }
        $effective = $effectiveId === $configured->zone_set_version_id ? $configured : $this->zoneVersion($effectiveId, $hqId);
        if ($version->zone_policy === ZonePolicy::HIGHER_ZONE_RANK->value && ! $this->zoneRankPolicy->valid($effective->zones->pluck('rank')->all())) {
            $errors[] = new PricingValidationIssue(PricingValidationCode::ZONE_RANK_INCOMPLETE, 'zone_set_version_id');
        }

        return $errors;
    }

    private function zoneVersion(string $id, string $hqId): PricingZoneSetVersionRecord
    {
        $version = $this->pricingZoneRepository->findVisibleVersionWithZones($hqId, $id);
        if ($version === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $version;
    }

    /** @return list<PricingValidationIssue> */
    private function periodIssues(TariffVersionRecord $version): array
    {
        $errors = [];
        if ($version->valid_from === null) {
            $errors[] = new PricingValidationIssue(PricingValidationCode::VALID_FROM_REQUIRED, 'valid_from');
        }
        if ($version->valid_from !== null && $version->valid_to !== null && $version->valid_to <= $version->valid_from) {
            $errors[] = new PricingValidationIssue(PricingValidationCode::EFFECTIVE_INTERVAL_INVALID, 'valid_to');
        }
        $period = new PricingVersionPeriodDto($version->tariff_version_id, $version->tariff_family_id, $version->version_number, $version->valid_from, $version->valid_to);
        if ($this->pricingVersionGuard->hasVersionOverlap(PricingResource::Tariffs, $period)) {
            $errors[] = new PricingValidationIssue(PricingValidationCode::EFFECTIVE_INTERVAL_OVERLAP, 'valid_from');
        }

        return $errors;
    }

    /** @param list<TariffRuleDefinition> $rules @return list<PricingValidationIssue> */
    private function ruleIssues(TariffVersionRecord $version, string $hqId, array $rules): array
    {
        if ($rules === []) {
            return [new PricingValidationIssue(PricingValidationCode::RULE_NOT_FOUND, 'rules')];
        }
        $selections = [];
        foreach ($rules as $rule) {
            $selections[] = new CatalogSelectionDto($rule->selector->offeringVersionId, $rule->selector->optionVersionId);
        }
        $availability = $this->catalogSelectionInspector->inspect($hqId, $selections);
        $errors = [];
        foreach ($rules as $index => $rule) {
            if ($version->family->tariff_kind === 'FREIGHT' && ! $availability[$index]->offeringPublished) {
                $errors[] = new PricingValidationIssue(PricingValidationCode::SERVICE_VERSION_NOT_PUBLISHED, 'rules');
            }
            if (! $availability[$index]->optionBound) {
                $errors[] = new PricingValidationIssue(PricingValidationCode::SERVICE_OPTION_NOT_BOUND, 'rules');
            }
            $errors = [...$errors, ...$this->tariffRuleValidator->validateRule($rule)];
        }

        return [...$errors, ...$this->tariffRuleValidator->conflicts($rules)];
    }

    /** @param list<TariffRuleDefinition> $rules @return list<PricingValidationIssue> */
    private function dependencyIssues(TariffVersionRecord $version, string $hqId, array $rules): array
    {
        // A missing start date has its own issue; never invent a validation date.
        if ($version->valid_from === null) {
            return [];
        }
        $chargeTypeIds = [];
        foreach ($rules as $rule) {
            $chargeTypeIds[] = $rule->selector->chargeTypeId;
        }
        $resolution = $this->serviceTariffDependencies->inspect($version->serviceAttachments->pluck('service_tariff_family_id')->all(), $hqId,
            $version->zone_set_version_id, $version->valid_from->max(CarbonImmutable::instance($this->clock->now())), $chargeTypeIds);
        if ($resolution->failure === null) {
            return [];
        }

        return [new PricingValidationIssue(PricingValidationCode::SERVICE_DEPENDENCY_INVALID, 'service_tariff_family_ids', ApiMessage::translate($resolution->failure->messageKey()))];
    }
}
