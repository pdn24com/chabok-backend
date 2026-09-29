<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\ServiceCatalog\Application\Contracts\OfferingOptionsInterface;
use Modules\ServiceCatalog\Application\Dto\SelectedServiceOptionDto;
use Modules\ServiceCatalog\Application\Mappers\OfferingConditionInput;
use Modules\ServiceCatalog\Domain\Policies\OfferingConditions;
use Modules\ServiceCatalog\Domain\ValueObjects\OfferingSelectionContext;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;

final readonly class OfferingOptions implements OfferingOptionsInterface
{
    public function __construct(
        private OfferingConditions $offeringConditions,
    ) {}

    /** @return list<SelectedServiceOptionDto> */
    public function resolvedOptions(ServiceOfferingVersionRecord $offering, OfferingSelectionContext $context): array
    {
        $rules = $offering->optionRules;
        $owner = $offering->hq_id;
        $results = [];
        foreach ($rules as $rule) {
            $option = $rule->optionVersion?->option;
            $current = $option?->publishedVersions->first();
            $available = $current !== null && $option->status === 'ACTIVE'
                && ($owner === null || $option->hq_id === null || $option->hq_id === $owner);
            if (! $available) {
                if ($rule->compatibility === 'REQUIRED') {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.catalog_dependency_is_inactive_unavailable', details: ['reason_code' => 'CATALOG_DEPENDENCY_UNAVAILABLE', 'resource' => 'options']);
                }

                continue;
            }
            $conditionMet = $rule->compatibility !== 'CONDITIONAL' || $this->offeringConditions->conditionPasses(OfferingConditionInput::fromArray($rule->condition ?? []), $context);
            $results[] = new SelectedServiceOptionDto($current->service_option_id, $current->service_option_version_id, $option->code,
                $current->labels, $current->definition, $rule->compatibility, $rule->compatibility === 'REQUIRED',
                $rule->compatibility !== 'FORBIDDEN' && $conditionMet, $conditionMet ? null : 'SERVICE_OPTION_CONDITION_NOT_MET');
        }

        return $results;
    }
}
