<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ValidateCatalogDraft;

use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogReaderInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogResourceDefinitionInterface;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;
use Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepositoryInterface;
use Modules\ServiceCatalog\Domain\Enums\CatalogResource;
use Modules\ServiceCatalog\Domain\Enums\CatalogValidationCode;
use Modules\ServiceCatalog\Domain\ValueObjects\CatalogValidationIssue;
use Modules\ServiceCatalog\Domain\ValueObjects\CatalogValidationResult;

final readonly class ValidateCatalogDraftHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private CatalogReaderInterface $catalogReader,
        private CatalogRepositoryInterface $catalogRepository,
        private CatalogResourceDefinitionInterface $catalogResourceDefinition,
        private CommitmentScheduleRepositoryInterface $commitmentScheduleRepository,
    ) {}

    public function handle(ValidateCatalogDraftCommand $command): CatalogValidationResult
    {
        $actor = $command->actor;
        $resource = $command->resource;
        $versionIdValue = $command->versionIdValue;
        $automatic = $command->automatic;
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.manage_draft');
        $row = $this->catalogReader->versionDetail($actor, $resource, $versionIdValue);
        $errors = [];
        if (empty($row->labels) || ! is_array($row->labels)) {
            $errors[] = new CatalogValidationIssue(CatalogValidationCode::ServiceLabelRequired, 'labels');
        }
        if ($row->valid_to && $row->valid_from && $row->valid_to <= $row->valid_from) {
            $errors[] = new CatalogValidationIssue(CatalogValidationCode::ServiceEffectiveIntervalInvalid, 'valid_to');
        }
        if ($resource === 'offerings') {
            foreach (['service_type_version_id', 'shipping_method_version_id'] as $field) {
                $dependencyResource = $field === 'service_type_version_id' ? 'service-types' : 'shipping-methods';
                if (! $this->catalogRepository->versionPublished($this->catalogResourceDefinition->resource($dependencyResource), (string) $row->getAttribute($field))) {
                    $errors[] = new CatalogValidationIssue(CatalogValidationCode::ServiceDependencyNotPublished, $field);
                }
            }
            if ($row->availabilityBindings->isEmpty()) {
                $errors[] = new CatalogValidationIssue(CatalogValidationCode::ServiceAvailabilityRequired, 'availability_bindings');
            }
            $publishedOptions = $this->catalogRepository->publishedVersionIdsAmong(CatalogResource::Option, $row->optionRules->pluck('service_option_version_id')->all());
            foreach ($row->optionRules as $rule) {
                if (! in_array($rule->service_option_version_id, $publishedOptions, true)) {
                    $errors[] = new CatalogValidationIssue(CatalogValidationCode::ServiceOptionVersionNotPublished, 'option_rules');
                }
                if ($rule->compatibility === 'CONDITIONAL' && empty($rule->condition)) {
                    $errors[] = new CatalogValidationIssue(CatalogValidationCode::ServiceOptionConditionRequired, 'option_rules');
                }
            }
            $binding = $row->commitmentBinding ?? null;
            if ($binding !== null) {
                if (! $this->commitmentScheduleRepository->publishedVersionExists($actor->hqId, (string) $binding->commitment_schedule_version_id)) {
                    $errors[] = new CatalogValidationIssue(CatalogValidationCode::CommitmentScheduleVersionNotPublished, 'commitment_binding.commitment_schedule_version_id');
                }
                if ($binding->delivery_mode === 'COMPUTED' && (empty($binding->duration_value) || empty($binding->duration_unit) || empty($binding->duration_anchor))) {
                    $errors[] = new CatalogValidationIssue(CatalogValidationCode::ComputedDeliveryConfigurationRequired, 'commitment_binding');
                }
                if ($binding->delivery_mode === 'COMPUTED' && $binding->pickup_mode === 'NONE' && in_array($binding->duration_anchor, ['PICKUP_COMMITMENT_START', 'PICKUP_COMMITMENT_END'], true)) {
                    $errors[] = new CatalogValidationIssue(CatalogValidationCode::ComputedDeliveryAnchorUnavailable, 'commitment_binding.duration_anchor');
                }
            }
        }
        $kind = $this->catalogResourceDefinition->resource($resource);
        $overlap = $this->catalogRepository->hasOverlappingEffectiveVersion($kind, (string) $row->getAttribute($kind->identityKey()),
            (string) $row->getAttribute($kind->versionKey()), $row->valid_from, $row->valid_to);
        if ($overlap && ! $automatic) {
            $errors[] = new CatalogValidationIssue(CatalogValidationCode::ServiceEffectiveIntervalOverlap, 'valid_from');
        }

        return new CatalogValidationResult($errors);
    }
}
