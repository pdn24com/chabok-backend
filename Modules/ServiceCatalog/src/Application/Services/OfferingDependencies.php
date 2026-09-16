<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

final readonly class OfferingDependencies
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\CurrentCatalog $currentCatalog,
        private \Modules\ServiceCatalog\Application\Repositories\CatalogRepository $catalog,
    )
    {
    }

    public function runtimeDependencies(array $row, string $hqId): array
    {
        foreach (['service_type_version_id' => 'service-types', 'shipping_method_version_id' => 'shipping-methods'] as $field => $resource) {
            $dependency = $this->currentCatalog->resolve($resource, (string) $row[$field], $hqId);
            $row[$field] = $dependency[$field];
            $row[str_replace('_version_id', '_labels', $field)] = json_decode($dependency['labels'], true);
        }
        return $row;
    }

    public function available(string $versionId, string $hqId, string $channel): bool
    {
        $bindings = $this->catalog->enabledAvailabilityBindings($versionId);
        return (bool) array_filter($bindings, fn($b) => $b->scope_type === 'PLATFORM' || $b->scope_type === 'TENANT' && $b->scope_value === $hqId || $b->scope_type === 'CHANNEL' && $b->scope_value === $channel);
    }
}
