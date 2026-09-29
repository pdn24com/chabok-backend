<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\UseCases\TransitionOpportunityStep;

use Illuminate\Database\ConnectionInterface;
use Modules\CrmOpportunitie\Application\Contracts\OpportunityAccessGuardInterface;
use Modules\CrmOpportunitie\Application\Dto\StepTransitionDto;
use Modules\CrmOpportunitie\Application\Repositories\FunnelStepRepositoryInterface;
use Modules\CrmOpportunitie\Application\Repositories\OpportunityEventRepositoryInterface;
use Modules\CrmOpportunitie\Application\Repositories\OpportunityRepositoryInterface;
use Modules\CrmOpportunitie\Application\Repositories\SalesFunnelRepositoryInterface;
use Modules\CrmOpportunitie\Domain\Enums\FunnelStepOutcome;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\FunnelStepRecord;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\OpportunityRecord;
use Modules\CrmTask\Application\Repositories\ActivityRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

/**
 * Moves an opportunity from the step the operator was looking at to the next one. The record carries no
 * version column, so the step the caller believed it stood on is what guards the move: a second operator
 * who moved it first changes that step, and the later move is refused instead of quietly overwriting.
 *
 * Winning is the one move with conditions of its own. A deal is only won against a record that has been
 * promoted to a customer, and only with the interaction that proves the acceptance named beside it.
 */
final readonly class TransitionOpportunityStepHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private OpportunityAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private ActivityRepositoryInterface $activityRepository,
        private SalesFunnelRepositoryInterface $salesFunnelRepository,
        private FunnelStepRepositoryInterface $funnelStepRepository,
        private OpportunityRepositoryInterface $opportunityRepository,
        private OpportunityEventRepositoryInterface $opportunityEventRepository,
    ) {}

    public function handle(TransitionOpportunityStepCommand $command): TransitionOpportunityStepResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);
        $input = $command->input;
        $at = $this->clock->now();
        $occurredAt = $input->occurredAt ?? $at;
        // A move is recorded as having happened, so it cannot have happened later than now.
        if ($occurredAt > $at) {
            throw $this->invalid('occurred_at', 'opportunity.transition_cannot_be_in_the_future');
        }

        return $this->connection->transaction(function () use ($command, $hqId, $input, $at, $occurredAt): TransitionOpportunityStepResult {
            // An opportunity of another tenant is indistinguishable from one that does not exist. The row
            // is locked for the whole transaction, so the step check below cannot be raced past.
            $opportunity = $this->opportunityRepository->lockForTenant($hqId, $command->opportunityId)
                ?? throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');

            if ((string) $opportunity->current_step_id !== $input->fromStepId) {
                throw new ApiException(ApiErrorCode::VersionConflict, 409, 'opportunity.step_changed_since_loaded',
                    details: ['current_step_id' => (string) $opportunity->current_step_id]);
            }
            if ($input->fromStepId === $input->toStepId) {
                throw $this->invalid('to_step_id', 'opportunity.transition_must_change_the_step');
            }

            $funnelId = (string) $opportunity->funnel_id;
            $from = $this->funnelStepRepository->findInFunnel($hqId, $funnelId, $input->fromStepId)
                ?? throw $this->invalid('from_step_id', 'opportunity.select_step_of_the_same_funnel');
            $to = $this->funnelStepRepository->findInFunnel($hqId, $funnelId, $input->toStepId);
            if ($to === null || ! $to->is_active) {
                throw $this->invalid('to_step_id', 'opportunity.select_active_step_of_the_same_funnel');
            }

            $this->assertEvidenceFits($hqId, $opportunity, $to, $input);
            $this->assertCloseReasonFits($to, $input);

            $funnel = $this->salesFunnelRepository->findForTenant($hqId, $funnelId)
                ?? throw new ApiException(ApiErrorCode::InternalServerError, 500, 'common.unexpected_error_occurred');

            // A step that closes the opportunity keeps the reason it closed on; reopening drops it, so a
            // stale reason never outlives the outcome that explained it.
            $this->opportunityRepository->update($hqId, $command->opportunityId, [
                'current_step_id' => $to->sales_funnel_step_id,
                'close_reason' => $to->outcome_type === FunnelStepOutcome::OPEN ? null : $input->closeReason,
            ]);

            $event = $this->opportunityEventRepository->create([
                'hq_id' => $hqId,
                'opportunity_id' => $command->opportunityId,
                'actor_id' => $command->actor->userId,
                'occurred_at' => $occurredAt,
                'reason' => $input->reason,
                'evidence_activity_id' => $input->evidenceActivityId,
                // Both ends are written as they read right now, so renaming a step later never rewrites
                // the history of what an operator actually did.
                'from_funnel_id' => $funnel->sales_funnel_id,
                'from_step_id' => $from->sales_funnel_step_id,
                'from_funnel_code' => $funnel->code,
                'from_funnel_title' => $funnel->title,
                'from_step_code' => $from->code,
                'from_step_title' => $from->title,
                'from_outcome_type' => $from->outcome_type->value,
                'to_funnel_id' => $funnel->sales_funnel_id,
                'to_step_id' => $to->sales_funnel_step_id,
                'to_funnel_code' => $funnel->code,
                'to_funnel_title' => $funnel->title,
                'to_step_code' => $to->code,
                'to_step_title' => $to->title,
                'to_outcome_type' => $to->outcome_type->value,
                'created_by' => $command->actor->userId,
                'created_at' => $at,
            ]);

            $stored = $this->opportunityRepository->findForTenant($hqId, $command->opportunityId) ?? $opportunity;

            return new TransitionOpportunityStepResult($stored, $event);
        }, attempts: 3);
    }

    /** Winning demands a promoted customer and the interaction that proves the acceptance. */
    private function assertEvidenceFits(string $hqId, OpportunityRecord $opportunity, FunnelStepRecord $to, StepTransitionDto $input): void
    {
        $customerId = (string) $opportunity->customer_id;
        if ($to->outcome_type === FunnelStepOutcome::WON) {
            if (! $this->customerRepository->isInCustomerPhase($hqId, $customerId)) {
                throw $this->invalid('to_step_id', 'opportunity.winning_needs_a_promoted_customer');
            }
            if ($input->evidenceActivityId === null) {
                throw $this->invalid('evidence_activity_id', 'opportunity.winning_needs_acceptance_evidence');
            }
        }
        if ($input->evidenceActivityId !== null
            && ! $this->activityRepository->existsForCustomer($hqId, $customerId, $input->evidenceActivityId)) {
            throw $this->invalid('evidence_activity_id', 'opportunity.select_interaction_of_the_same_customer');
        }
    }

    /** A reason for closing belongs only to a move that closes the opportunity. */
    private function assertCloseReasonFits(FunnelStepRecord $to, StepTransitionDto $input): void
    {
        if ($input->closeReason !== null && $to->outcome_type === FunnelStepOutcome::OPEN) {
            throw $this->invalid('close_reason', 'opportunity.close_reason_needs_a_closing_step');
        }
    }

    private function invalid(string $field, string $messageKey): ApiException
    {
        return new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', [$field => [$messageKey]]);
    }
}
