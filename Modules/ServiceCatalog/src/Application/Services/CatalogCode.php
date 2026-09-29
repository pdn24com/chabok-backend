<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Organization\Application\Repositories\TenantRepositoryInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogCodeInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogResourceDefinitionInterface;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;
use RuntimeException;

final readonly class CatalogCode implements CatalogCodeInterface
{
    public function __construct(
        private CatalogRepositoryInterface $catalogRepository,
        private TenantRepositoryInterface $tenantRepository,
        private CatalogResourceDefinitionInterface $catalogResourceDefinition,
    ) {}

    public function generate(string $resource, string $owner): string
    {
        // Callers are in a transaction; serialize automatic allocation per tenant.
        $kind = $this->catalogResourceDefinition->resource($resource);
        $this->tenantRepository->lockIdentity($owner);
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $code = (string) random_int(100000, 999999);
            if (! $this->catalogRepository->codeTaken($kind, $owner, $code)) {
                return $code;
            }
        }
        throw new RuntimeException('Unable to allocate catalog code.');
    }
}
