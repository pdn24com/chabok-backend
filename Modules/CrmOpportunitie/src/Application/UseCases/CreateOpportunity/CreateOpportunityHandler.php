<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\UseCases\CreateOpportunity;

use Illuminate\Database\ConnectionInterface;
use Modules\CrmOpportunitie\Application\Contracts\OpportunityAccessGuardInterface;
use Modules\CrmOpportunitie\Application\Repositories\FunnelStepRepositoryInterface;
use Modules\CrmOpportunitie\Application\Repositories\OpportunityEventRepositoryInterface;
use Modules\CrmOpportunitie\Application\Repositories\OpportunityRepositoryInterface;
use Modules\CrmOpportunitie\Application\Repositories\SalesFunnelRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;

/**
 * Opens an opportunity on the first step of its funnel. The starting step is the server's to choose, so
 * nothing can be filed halfway down the pipeline, and the opening is written to the history at once: an
 * opportunity whose first entry is a later move would have no record of when it began.
 */
final readonly class CreateOpportunityHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private OpportunityAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private UserRepositoryInterface $userRepository,
        private SalesFunnelRepositoryInterface $salesFunnelRepository,
        private FunnelStepRepositoryInterface $funnelStepRepository,
        private OpportunityRepositoryInterface $opportunityRepository,
        private OpportunityEventRepositoryInterface $opportunityEventRepository,
    ) {}

    public function handle(CreateOpportunityCommand $command): CreateOpportunityResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);
        $input = $command->input;

        return $this->connection->transaction(function () use ($command, $hqId, $input): CreateOpportunityResult {
            // A lead is as good as a customer here: the pipeline is what promotes the one into the other.
            if (! $this->customerRepository->existsForTenant($hqId, $input->customerId)) {
                throw $this->invalid('customer_id', 'opportunity.select_customer_of_the_tenant');
            }
            if ($this->userRepository->findByTenant($hqId, $input->assigneeId)?->status !== 'ACTIVE') {
                throw $this->invalid('assignee_id', 'opportunity.select_active_assignee_from_tenant_users');
            }
            $funnel = $this->salesFunnelRepository->findForTenant($hqId, $input->funnelId);
            if ($funnel === null || ! $funnel->is_active) {
                throw $this->invalid('funnel_id', 'opportunity.select_active_funnel');
            }
            // A funnel whose steps were all retired can hold nothing, and silently filing on a retired
            // step would put the card on a column the board never draws.
            $step = $this->funnelStepRepository->firstForFunnel($hqId, $input->funnelId)
                ?? throw $this->invalid('funnel_id', 'opportunity.funnel_has_no_active_step');

            $at = $this->clock->now();
            $opportunity = $this->opportunityRepository->create([
                'hq_id' => $hqId,
                'customer_id' => $input->customerId,
                'funnel_id' => $input->funnelId,
                'current_step_id' => $step->sales_funnel_step_id,
                'assignee_id' => $input->assigneeId,
                'title' => $input->title,
                'amount' => $input->amount,
                'probability' => $input->probability,
                // A calendar day, so only the date part of the submitted instant is kept.
                'expected_close' => $input->expectedClose?->format('Y-m-d'),
                'created_by' => $command->actor->userId,
                'created_at' => $at,
            ]);

            // The whole from_* group stays null: there is no step the opportunity came from.
            $event = $this->opportunityEventRepository->create([
                'hq_id' => $hqId,
                'opportunity_id' => $opportunity->opportunity_id,
                'actor_id' => $command->actor->userId,
                'occurred_at' => $at,
                'to_funnel_id' => $funnel->sales_funnel_id,
                'to_step_id' => $step->sales_funnel_step_id,
                'to_funnel_code' => $funnel->code,
                'to_funnel_title' => $funnel->title,
                'to_step_code' => $step->code,
                'to_step_title' => $step->title,
                'to_outcome_type' => $step->outcome_type->value,
                'created_by' => $command->actor->userId,
                'created_at' => $at,
            ]);

            // Read back, so the response names the step instead of carrying its ID alone.
            $stored = $this->opportunityRepository->findForTenant($hqId, $opportunity->opportunity_id) ?? $opportunity;

            return new CreateOpportunityResult($stored, $event);
        }, attempts: 3);
    }

    private function invalid(string $field, string $messageKey): ApiException
    {
        return new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', [$field => [$messageKey]]);
    }
}
