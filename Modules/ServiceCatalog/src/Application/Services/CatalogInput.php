<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Carbon\CarbonImmutable;

final readonly class CatalogInput
{
    public function versionColumns(string $resource, array $input): array
    {
        $base = [
            'labels' => json_encode($input['labels'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'description' => $input['description'] ?? null,
            'valid_from' => $this->databaseTimestamp($input['valid_from'] ?? null),
            'valid_to' => $this->databaseTimestamp($input['valid_to'] ?? null),
        ];
        if ($resource !== 'offerings') {
            return $base + ['definition' => json_encode($input['definition'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)];
        }
        return $base + [
            'service_type_version_id' => $input['service_type_version_id'],
            'shipping_method_version_id' => $input['shipping_method_version_id'],
            'sla_policy' => json_encode($input['sla_policy'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'availability_summary' => json_encode($input['availability_summary'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ];
    }

    public function databaseTimestamp(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : CarbonImmutable::parse((string) $value)->utc()->format('Y-m-d H:i:s.u');
    }
}
