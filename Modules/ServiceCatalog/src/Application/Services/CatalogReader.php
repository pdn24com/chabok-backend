<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CatalogReader
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\CatalogResourceDefinition $catalogResourceDefinition,
        private \Modules\ServiceCatalog\Application\Repositories\CatalogRepository $catalog,
    )
    {
    }

    public function versionDetail(AuthenticatedPrincipal $actor, string $resource, string $versionIdValue): array
    {
        [$identityId, $versionId] = $this->catalogResourceDefinition->map($resource);
        $row = $this->catalog->versionDetail($actor->hqId, $resource, $versionIdValue);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        $result = $this->decode((array) $row);
        if ($resource === 'offerings') {
            $result['option_rules'] = array_map(fn($row) => $this->decode((array) $row), $this->catalog->optionRules($versionIdValue));
            $result['eligibility_rules'] = array_map(fn($row) => $this->decode((array) $row), $this->catalog->eligibilityRules($versionIdValue));
            $result['coverage_references'] = array_map(fn($row) => $this->decode((array) $row), $this->catalog->coverageReferences($versionIdValue));
            $result['availability_bindings'] = array_map(fn($row) => $this->decode((array) $row), $this->catalog->availabilityBindings($versionIdValue));
            $binding = $this->catalog->commitmentBinding($versionIdValue);
            $result['commitment_binding'] = $binding === null ? null : (array) $binding;
        }
        return $result;
    }

    public function decode(array $row): array
    {
        foreach (['labels', 'definition', 'sla_policy', 'availability_summary', 'condition', 'expected_value'] as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $row[$field] = json_decode($row[$field], true);
            }
        }
        return $row;
    }

    public function visibleIdentity(AuthenticatedPrincipal $actor, string $resource, string $value): void
    {
        if (!$this->catalog->identityVisible($actor->hqId, $resource, $value)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
    }
}
