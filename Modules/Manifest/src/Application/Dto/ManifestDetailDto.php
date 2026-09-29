<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Dto;

use Illuminate\Database\Eloquent\Collection;
use Modules\Audit\Infrastructure\Persistence\Models\AuditEventRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\CustodyEventRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\StatusEventRecord;
use Modules\Manifest\Domain\Enums\ManifestEligibilityReason;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestParcelRecord;

final readonly class ManifestDetailDto
{
    /**
     * @param  Collection<int, ManifestParcelRecord>  $parcels
     * @param  array<string, ManifestEligibilityReason>  $eligibilities
     * @param  list<string>  $permittedActions
     * @param  Collection<int, StatusEventRecord>  $statusEvents
     * @param  Collection<int, CustodyEventRecord>  $custodyEvents
     * @param  Collection<int, AuditEventRecord>  $timeline
     */
    public function __construct(
        public ManifestListItemDto $list,
        public Collection $parcels,
        public array $eligibilities,
        public array $permittedActions,
        public Collection $statusEvents,
        public Collection $custodyEvents,
        public Collection $timeline,
        public ?ManifestExceptionStateDto $exceptionState,
    ) {}
}
