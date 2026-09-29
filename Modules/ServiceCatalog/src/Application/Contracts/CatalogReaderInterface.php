<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOptionVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceTypeVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ShippingMethodVersionRecord;

interface CatalogReaderInterface
{
    public function versionDetail(AuthenticatedPrincipal $actor, string $resource, string $versionIdValue): ServiceTypeVersionRecord|ShippingMethodVersionRecord|ServiceOfferingVersionRecord|ServiceOptionVersionRecord;

    public function visibleIdentity(AuthenticatedPrincipal $actor, string $resource, string $value): void;
}
