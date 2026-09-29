<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\ServiceCatalog\Application\Contracts\OfferingDependenciesInterface;
use Modules\ServiceCatalog\Application\Dto\OfferingDependencyVersionsDto;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;
use Modules\ServiceCatalog\Domain\Enums\CatalogResource;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;

final readonly class OfferingDependencies implements OfferingDependenciesInterface
{
    public function __construct(
        private CatalogRepositoryInterface $catalogRepository,
    ) {}

    /** @param Collection<int, ServiceOfferingVersionRecord> $offerings @return array<string, OfferingDependencyVersionsDto> */
    public function currentFor(Collection $offerings, string $hqId): array
    {
        if ($offerings->isEmpty()) {
            return [];
        }
        $typeIds = $offerings->map(static fn ($offering) => $offering->serviceTypeVersion->service_type_id)->unique();
        $methodIds = $offerings->map(static fn ($offering) => $offering->shippingMethodVersion->shipping_method_id)->unique();
        $types = $this->catalogRepository->publishedVersionsOfVisibleIdentities(CatalogResource::ServiceType, $typeIds->values()->all(), $hqId);
        $methods = $this->catalogRepository->publishedVersionsOfVisibleIdentities(CatalogResource::ShippingMethod, $methodIds->values()->all(), $hqId);
        $result = [];
        foreach ($offerings as $offering) {
            $result[$offering->service_offering_version_id] = new OfferingDependencyVersionsDto(
                $types->get($offering->serviceTypeVersion->service_type_id)?->first(),
                $methods->get($offering->shippingMethodVersion->shipping_method_id)?->first());
        }

        return $result;
    }

    public function assertAvailable(OfferingDependencyVersionsDto $dependencies): void
    {
        if (! $dependencies->available()) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.catalog_dependency_is_inactive_unavailable',
                details: ['reason_code' => 'CATALOG_DEPENDENCY_UNAVAILABLE', 'resource' => $dependencies->type === null ? 'service-types' : 'shipping-methods']);
        }
    }

    public function available(
        ServiceOfferingVersionRecord $version,
        string $hqId,
        string $channel,
    ): bool {
        $bindings = $version->availabilityBindings->filter(fn ($binding) => (bool) $binding->enabled);

        return $bindings->contains(fn ($binding) => $binding->scope_type === 'PLATFORM' || $binding->scope_type === 'TENANT' && $binding->scope_value === $hqId || $binding->scope_type === 'CHANNEL' && $binding->scope_value === $channel);
    }
}
