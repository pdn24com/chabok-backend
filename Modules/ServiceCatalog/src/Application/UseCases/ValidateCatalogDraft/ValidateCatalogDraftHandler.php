<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ValidateCatalogDraft;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ValidateCatalogDraftHandler
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\CatalogAccessGuard $catalogAccessGuard,
        private \Modules\ServiceCatalog\Application\Services\CatalogReader $catalogReader,
        private \Modules\ServiceCatalog\Application\Repositories\CatalogRepository $catalog,
    )
    {
    }

    public function handle(ValidateCatalogDraftCommand $command): ValidateCatalogDraftResult
    {
        return new ValidateCatalogDraftResult($this->execute($command->actor, $command->resource, $command->versionIdValue, $command->automatic));
    }

    private function execute(AuthenticatedPrincipal $actor, string $resource, string $versionIdValue, bool $automatic = false): array
    {
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.manage_draft');
        $row = $this->catalogReader->versionDetail($actor, $resource, $versionIdValue);
        $errors = [];
        if (empty($row['labels']) || !is_array($row['labels'])) {
            $errors[] = ['code' => 'SERVICE_LABEL_REQUIRED', 'field' => 'labels'];
        }
        if ($row['valid_to'] && $row['valid_from'] && $row['valid_to'] <= $row['valid_from']) {
            $errors[] = ['code' => 'SERVICE_EFFECTIVE_INTERVAL_INVALID', 'field' => 'valid_to'];
        }
        if ($resource === 'offerings') {
            foreach (['service_type_version_id', 'shipping_method_version_id'] as $field) {
                $dependencyResource = $field === 'service_type_version_id' ? 'service-types' : 'shipping-methods';
                if (!$this->catalog->publishedVersionExists($dependencyResource, $row[$field])) {
                    $errors[] = ['code' => 'SERVICE_DEPENDENCY_NOT_PUBLISHED', 'field' => $field];
                }
            }
            if ($row['availability_bindings'] === []) {
                $errors[] = ['code' => 'SERVICE_AVAILABILITY_REQUIRED', 'field' => 'availability_bindings'];
            }
            foreach ($row['option_rules'] as $rule) {
                if (!$this->catalog->publishedVersionExists('options', $rule['service_option_version_id'])) {
                    $errors[] = ['code' => 'SERVICE_OPTION_VERSION_NOT_PUBLISHED', 'field' => 'option_rules'];
                }
                if ($rule['compatibility'] === 'CONDITIONAL' && empty($rule['condition'])) {
                    $errors[] = ['code' => 'SERVICE_OPTION_CONDITION_REQUIRED', 'field' => 'option_rules'];
                }
            }
            $binding = $row['commitment_binding'] ?? null;
            if ($binding !== null) {
                if (!$this->catalog->publishedScheduleExists($actor->hqId, $binding['commitment_schedule_version_id'])) {
                    $errors[] = [
                        'code' => 'COMMITMENT_SCHEDULE_VERSION_NOT_PUBLISHED',
                        'field' => 'commitment_binding.commitment_schedule_version_id',
                    ];
                }
                if ($binding['delivery_mode'] === 'COMPUTED' && (empty($binding['duration_value']) || empty($binding['duration_unit']) || empty($binding['duration_anchor']))) {
                    $errors[] = ['code' => 'COMPUTED_DELIVERY_CONFIGURATION_REQUIRED', 'field' => 'commitment_binding'];
                }
                if ($binding['delivery_mode'] === 'COMPUTED' && $binding['pickup_mode'] === 'NONE' && in_array($binding['duration_anchor'], ['PICKUP_COMMITMENT_START', 'PICKUP_COMMITMENT_END'], true)) {
                    $errors[] = ['code' => 'COMPUTED_DELIVERY_ANCHOR_UNAVAILABLE', 'field' => 'commitment_binding.duration_anchor'];
                }
            }
        }
        $overlap = $this->catalog->hasEffectiveOverlap($resource, $row);
        if ($overlap && !$automatic) {
            $errors[] = ['code' => 'SERVICE_EFFECTIVE_INTERVAL_OVERLAP', 'field' => 'valid_from'];
        }
        return ['valid' => $errors === [], 'errors' => $errors];
    }
}
