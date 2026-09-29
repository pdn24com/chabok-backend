<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\CoverageAddress;
use Modules\ServiceCatalog\Application\Contracts\CurrentCatalogInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingEligibilityInterface;
use Modules\ServiceCatalog\Application\Dto\OptionRevisionSetDto;
use Modules\ServiceCatalog\Application\Mappers\OfferingConditionInput;
use Modules\ServiceCatalog\Domain\Enums\EligibilityOutcome;
use Modules\ServiceCatalog\Domain\Policies\OfferingConditions;
use Modules\ServiceCatalog\Domain\Support\OfferingFacts;
use Modules\ServiceCatalog\Domain\ValueObjects\OfferingEligibilityDecision;
use Modules\ServiceCatalog\Domain\ValueObjects\OfferingSelectionContext;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\OfferingOptionRuleRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;

final readonly class OfferingEligibility implements OfferingEligibilityInterface
{
    public function __construct(
        private OfferingConditions $offeringConditions,
        private CurrentCatalogInterface $currentCatalog,
        private ClockInterface $clock,
    ) {}

    /** @param list<OptionRevisionSetDto>|null $selectedOptions */
    public function evaluate(ServiceOfferingVersionRecord $row, OfferingSelectionContext $context, ?array $selectedOptions = null): OfferingEligibilityDecision
    {
        $reasons = [];
        $missing = [];
        if (! $this->coverageMatches($row, 'ORIGIN', $context->sender)) {
            $reasons[] = 'SERVICE_COVERAGE_UNSUPPORTED';
        }
        if (! $this->coverageMatches($row, 'DESTINATION', $context->receiver)) {
            $reasons[] = 'SERVICE_COVERAGE_UNSUPPORTED';
        }
        foreach ($row->eligibilityRules as $rule) {
            $actual = OfferingFacts::value($context, (string) $rule->fact_key);
            if ($actual === null) {
                $missing[] = $rule->fact_key;

                continue;
            }
            $passes = $this->offeringConditions->conditionPasses(OfferingConditionInput::fromRule($rule), $context);
            if (! $passes) {
                $reasons[] = $rule->reason_code;
            }
        }
        $selected = [];
        foreach ($selectedOptions ?? $this->selectedOptions($context, $row->hq_id) as $revision) {
            if ($revision->currentVersionId === null) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.catalog_dependency_is_inactive_unavailable', details: ['reason_code' => 'CATALOG_DEPENDENCY_UNAVAILABLE', 'resource' => 'options']);
            }
            $selected[] = $revision->optionId;
        }
        $optionRules = $row->optionRules;
        $knownOptions = $optionRules->map(static fn (OfferingOptionRuleRecord $rule) => $rule->optionVersion?->service_option_id)->all();
        if (array_diff($selected, $knownOptions) !== []) {
            $reasons[] = 'SERVICE_OPTION_NOT_ALLOWED';
        }
        foreach ($optionRules as $rule) {
            $has = in_array($rule->optionVersion?->service_option_id, $selected, true);
            if ($rule->compatibility === 'REQUIRED' && ! $has) {
                $reasons[] = 'SERVICE_OPTION_REQUIRED';
            }
            if ($rule->compatibility === 'FORBIDDEN' && $has) {
                $reasons[] = 'SERVICE_OPTION_FORBIDDEN';
            }
            if ($rule->compatibility === 'CONDITIONAL' && $has && ! $this->offeringConditions->conditionPasses(OfferingConditionInput::fromArray($rule->condition ?? []), $context)) {
                $reasons[] = 'SERVICE_OPTION_CONDITION_NOT_MET';
            }
        }
        $outcome = $reasons !== [] ? EligibilityOutcome::Ineligible : ($missing !== [] ? EligibilityOutcome::Unknown : EligibilityOutcome::Eligible);

        return new OfferingEligibilityDecision($outcome, array_values(array_unique($reasons)), array_values(array_unique($missing)), CarbonImmutable::instance($this->clock->now())->utc()->toISOString());
    }

    /** @return list<OptionRevisionSetDto> */
    public function selectedOptions(OfferingSelectionContext $context, ?string $owner): array
    {
        $references = array_map('strval', array_values((array) ($context->selectedOptionVersionIds ?? [])));

        return $this->currentCatalog->optionRevisions($references, $owner);
    }

    private function coverageMatches(ServiceOfferingVersionRecord $offering, string $direction, CoverageAddress $address): bool
    {
        $hasCoverage = false;
        foreach ($offering->coverageReferences as $reference) {
            if (! in_array($reference->direction, [$direction, 'BOTH'], true)) {
                continue;
            }
            $hasCoverage = true;
            if ($this->offeringConditions->coverageMatches($reference->reference_type, $reference->reference_value, $reference->secondary_reference_value, $address)) {
                return true;
            }
        }

        return ! $hasCoverage;
    }
}
