<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Domain\ApiException;

final readonly class OfferingOptions
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\CatalogReader $catalogReader,
        private \Modules\ServiceCatalog\Application\Repositories\CatalogRepository $catalog,
        private \Modules\ServiceCatalog\Application\CurrentCatalog $currentCatalog,
        private \Modules\ServiceCatalog\Domain\OfferingConditions $offeringConditions,
    )
    {
    }

    public function resolvedOptions(string $offeringVersionId, array $context): array
    {
        $results = [];
        foreach (array_map(fn($row) => $this->catalogReader->decode((array) $row), $this->catalog->optionRules($offeringVersionId)) as $rule) {
            $owner = $this->catalog->offeringOwner($offeringVersionId);
            try {
                $current = $this->currentCatalog->resolve('options', (string) $rule['service_option_version_id'], $owner);
            } catch (ApiException $error) {
                if ($rule['compatibility'] === 'REQUIRED') {
                    throw $error;
                }
                continue;
            }
            $conditionMet = $rule['compatibility'] !== 'CONDITIONAL' || $this->offeringConditions->conditionPasses((array) $rule['condition'], $context);
            $results[] = [
                'service_option_id' => $current['service_option_id'],
                'service_option_version_id' => $current['service_option_version_id'],
                'code' => $current['code'],
                'labels' => json_decode($current['labels'], true),
                'definition' => json_decode($current['definition'], true),
                'compatibility' => $rule['compatibility'],
                'required' => $rule['compatibility'] === 'REQUIRED',
                'selectable' => $rule['compatibility'] !== 'FORBIDDEN' && $conditionMet,
                'reason_code' => $conditionMet ? null : 'SERVICE_OPTION_CONDITION_NOT_MET',
            ];
        }
        return $results;
    }
}
