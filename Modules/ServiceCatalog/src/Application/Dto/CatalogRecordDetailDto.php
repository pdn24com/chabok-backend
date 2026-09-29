<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOptionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOptionVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceTypeRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceTypeVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ShippingMethodRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ShippingMethodVersionRecord;

final readonly class CatalogRecordDetailDto
{
    public function __construct(public ServiceTypeRecord|ShippingMethodRecord|ServiceOfferingRecord|ServiceOptionRecord|CommitmentScheduleRecord $identity, public ServiceTypeVersionRecord|ShippingMethodVersionRecord|ServiceOfferingVersionRecord|ServiceOptionVersionRecord|CommitmentScheduleVersionRecord $version) {}
}
