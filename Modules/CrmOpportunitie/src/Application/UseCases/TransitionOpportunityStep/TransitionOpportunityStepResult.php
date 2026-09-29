<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\UseCases\TransitionOpportunityStep;

use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\OpportunityEventRecord;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\OpportunityRecord;

/** The opportunity on its new step, together with the entry the move wrote to the history. */
final readonly class TransitionOpportunityStepResult
{
    public function __construct(
        public OpportunityRecord $opportunity,
        public OpportunityEventRecord $event,
    ) {}
}
