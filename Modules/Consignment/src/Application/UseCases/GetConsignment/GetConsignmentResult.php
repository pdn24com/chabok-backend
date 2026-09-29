<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\GetConsignment;

use Illuminate\Database\Eloquent\Collection;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;

final readonly class GetConsignmentResult
{
    /** @param list<string> $nonPricingContactFields @param Collection<int, ManifestRecord> $manifests */
    public function __construct(
        public ConsignmentRecord $consignment,
        public bool $showParcels,
        public bool $showAudit,
        public bool $editable,
        public array $nonPricingContactFields,
        public Collection $manifests,
    ) {}
}
