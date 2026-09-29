<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\UseCases\TransitionOpportunityStep;

use Modules\CrmOpportunitie\Application\Dto\StepTransitionDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class TransitionOpportunityStepCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $opportunityId,
        public StepTransitionDto $input,
    ) {}
}
