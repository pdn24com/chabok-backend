<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\ServiceCatalog\Application\Contracts\OfferingChildrenWriterInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingReferenceGuardInterface;
use Modules\ServiceCatalog\Application\Dto\CatalogDraftDto;
use Modules\ServiceCatalog\Application\Repositories\OfferingChildrenRepositoryInterface;
use Modules\ServiceCatalog\Domain\Enums\OfferingChild;

final readonly class OfferingChildrenWriter implements OfferingChildrenWriterInterface
{
    public function __construct(
        private OfferingReferenceGuardInterface $offeringReferenceGuard,
        private OfferingChildrenRepositoryInterface $offeringChildrenRepository,
    ) {}

    public function replaceOfferingChildren(
        string $versionId,
        CatalogDraftDto $input,
        ?string $hqId,
    ): void {
        $this->offeringReferenceGuard->assertOfferingReferences($input, $hqId);
        $this->offeringChildrenRepository->deleteAll($versionId);
        $this->offeringChildrenRepository->insert(OfferingChild::OptionRule, $this->optionRules($versionId, $input));
        $this->offeringChildrenRepository->insert(OfferingChild::EligibilityRule, $this->eligibilityRules($versionId, $input));
        $this->offeringChildrenRepository->insert(OfferingChild::CoverageReference, $this->coverageReferences($versionId, $input));
        $this->offeringChildrenRepository->insert(OfferingChild::AvailabilityBinding, $this->availabilityBindings($versionId, $input, $hqId));
        $this->offeringChildrenRepository->insert(OfferingChild::CommitmentBinding, $this->commitmentBinding($versionId, $input));
    }

    public function cloneOfferingChildren(string $from, string $to): void
    {
        $this->offeringChildrenRepository->cloneAll($from, $to);
    }

    /** @return list<array<string, mixed>> */
    private function optionRules(string $versionId, CatalogDraftDto $input): array
    {
        return array_map(fn ($rule): array => [

            'service_offering_version_id' => $versionId,
            'service_option_version_id' => $rule->serviceOptionVersionId,
            'compatibility' => $rule->compatibility,
            'condition' => $rule->condition,
        ], $input->optionRules);
    }

    /** @return list<array<string, mixed>> */
    private function eligibilityRules(string $versionId, CatalogDraftDto $input): array
    {
        return array_map(fn ($rule): array => [

            'service_offering_version_id' => $versionId,
            'dimension' => $rule->dimension,
            'fact_key' => $rule->factKey,
            'operator' => $rule->operator,
            'expected_value' => $rule->expectedValue,
            'reason_code' => $rule->reasonCode,
            'priority' => $rule->priority ?? 100,
        ], $input->eligibilityRules);
    }

    /** A postal range is the only reference kind that carries a second bound. @return list<array<string, mixed>> */
    private function coverageReferences(string $versionId, CatalogDraftDto $input): array
    {
        return array_map(fn ($reference): array => [

            'service_offering_version_id' => $versionId,
            'direction' => $reference->direction,
            'reference_type' => $reference->referenceType,
            'reference_value' => $reference->referenceValue,
            'secondary_reference_value' => $reference->referenceType === 'POSTAL_RANGE' ? $reference->secondaryReferenceValue ?? null : null,
            'priority' => $reference->priority ?? 100,
        ], $input->coverageReferences);
    }

    /** A tenant-scoped binding takes the acting tenant; a platform binding is deliberately unscoped. @return list<array<string, mixed>> */
    private function availabilityBindings(string $versionId, CatalogDraftDto $input, ?string $hqId): array
    {
        return array_map(fn ($binding): array => [

            'service_offering_version_id' => $versionId,
            'scope_type' => $binding->scopeType,
            'scope_value' => match ($binding->scopeType) {
                'TENANT' => $hqId,
                'PLATFORM' => null,
                default => $binding->scopeValue ?? null,
            },
            'enabled' => $binding->enabled ?? true,
        ], $input->availabilityBindings);
    }

    /** Duration columns only apply to a computed delivery promise. @return list<array<string, mixed>> */
    private function commitmentBinding(string $versionId, CatalogDraftDto $input): array
    {
        $binding = $input->commitmentBinding;
        if ($binding === null) {
            return [];
        }
        $computed = $binding->deliveryMode === 'COMPUTED';

        return [[

            'service_offering_version_id' => $versionId,
            'commitment_schedule_version_id' => $binding->commitmentScheduleVersionId,
            'pickup_mode' => $binding->pickupMode,
            'delivery_mode' => $binding->deliveryMode,
            'duration_value' => $computed ? $binding->durationValue ?? null : null,
            'duration_unit' => $computed ? $binding->durationUnit ?? null : null,
            'duration_anchor' => $computed ? $binding->durationAnchor ?? null : null,
        ]];
    }
}
