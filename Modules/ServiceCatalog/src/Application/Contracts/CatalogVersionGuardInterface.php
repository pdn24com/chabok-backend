<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOptionVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceTypeVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ShippingMethodVersionRecord;

interface CatalogVersionGuardInterface
{
    public function approvalChanges(AuthenticatedPrincipal $actor, ServiceTypeVersionRecord|ShippingMethodVersionRecord|ServiceOfferingVersionRecord|ServiceOptionVersionRecord $row, string $resource, string $versionId): array;

    public function publicationChanges(AuthenticatedPrincipal $actor, ServiceTypeVersionRecord|ShippingMethodVersionRecord|ServiceOfferingVersionRecord|ServiceOptionVersionRecord $row, string $resource, string $versionId): array;

    public function simpleTransition(ServiceTypeVersionRecord|ShippingMethodVersionRecord|ServiceOfferingVersionRecord|ServiceOptionVersionRecord $row, string $from, string $to): array;
}
