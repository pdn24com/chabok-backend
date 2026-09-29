<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\UseCases\CreateOpportunity;

use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\OpportunityEventRecord;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\OpportunityRecord;

/** The opportunity as it now stands, together with the entry its opening wrote to the history. */
final readonly class CreateOpportunityResult
{
    public function __construct(
        public OpportunityRecord $opportunity,
        public OpportunityEventRecord $event,
    ) {}
}
