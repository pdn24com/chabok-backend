<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Carbon\CarbonImmutable;
use Modules\ServiceCatalog\Application\Contracts\CatalogInputInterface;
use Modules\ServiceCatalog\Application\Dto\CatalogDraftDto;

final readonly class CatalogInput implements CatalogInputInterface
{
    public function versionColumns(string $resource, CatalogDraftDto $input): array
    {
        $base = [
            'labels' => $input->labels ?? [],
            'description' => $input->description ?? null,
            'valid_from' => $this->databaseTimestamp($input->validFrom ?? null),
            'valid_to' => $this->databaseTimestamp($input->validTo ?? null),
        ];
        if ($resource !== 'offerings') {
            return $base + ['definition' => $input->definition ?? []];
        }

        return $base + [
            'service_type_version_id' => $input->serviceTypeVersionId,
            'shipping_method_version_id' => $input->shippingMethodVersionId,
            'sla_policy' => $input->slaPolicy ?? [],
            'availability_summary' => $input->availabilitySummary ?? [],
        ];
    }

    public function databaseTimestamp(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : CarbonImmutable::parse((string) $value)->utc()->format('Y-m-d H:i:s.u');
    }
}
