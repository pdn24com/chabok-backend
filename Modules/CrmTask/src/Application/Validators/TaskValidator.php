<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\Validators;

use DateTimeImmutable;
use Modules\CrmTask\Application\Contracts\ActivityValidatorInterface;
use Modules\CrmTask\Application\Contracts\TaskValidatorInterface;
use Modules\CrmTask\Application\Dto\TaskActionDto;
use Modules\CrmTask\Application\Dto\TaskAssignmentDto;
use Modules\CrmTask\Application\Dto\TaskDraftDto;
use Modules\CrmTask\Application\Ports\CustomerDirectoryInterface;
use Modules\CrmTask\Application\Ports\OpportunityDirectoryInterface;
use Modules\CrmTask\Application\Ports\TeamDirectoryInterface;
use Modules\CrmTask\Domain\Enums\TaskAssignmentEventType;
use Modules\CrmTask\Domain\Enums\TaskStatus;
use Modules\CrmTask\Infrastructure\Persistence\Models\TaskRecord;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;

final readonly class TaskValidator implements TaskValidatorInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private CustomerDirectoryInterface $customerDirectory,
        private OpportunityDirectoryInterface $opportunityDirectory,
        private TeamDirectoryInterface $teamDirectory,
        private ActivityValidatorInterface $activityValidator,
    ) {}

    public function validateDraft(string $hqId, TaskDraftDto $draft): void
    {
        $this->assertReminderFitsDeadline($draft->remindAt, $draft->dueAt);
        // The database refuses a reminder with no one to remind, so the gap is caught here first.
        if ($draft->remindAt !== null && $draft->assigneeId === null) {
            throw $this->invalid('remind_at', 'task.reminder_needs_an_owner');
        }
        $this->assertFiling($hqId, $draft->customerId, $draft->opportunityId);
        $this->assertActiveUser($hqId, $draft->assigneeId, 'assignee_id');
        $this->assertActiveTeam($hqId, $draft->teamContextId, 'team_context_id');
    }

    public function validateAssignment(string $hqId, TaskRecord $current, TaskAssignmentDto $assignment, string $actorId): void
    {
        if (in_array($current->status, [TaskStatus::COMPLETED, TaskStatus::CANCELLED], true)) {
            throw $this->invalid('*', 'task.closed_task_cannot_be_moved');
        }
        if ($assignment->eventType->requiresReason() && trim((string) $assignment->reason) === '') {
            throw $this->invalid('reason', 'task.moving_work_needs_a_reason');
        }
        // Claiming is taking the work yourself; handing it to a third party is a referral.
        if ($assignment->eventType === TaskAssignmentEventType::CLAIM && $assignment->toUserId !== $actorId) {
            throw $this->invalid('to_user_id', 'task.claim_takes_the_task_for_the_actor');
        }
        if ($assignment->expectedAssigneeSpecified && $assignment->expectedAssigneeId !== $current->assignee_id) {
            throw new ApiException(ApiErrorCode::Conflict, 409, 'task.assignment_changed_since_loaded');
        }
        $this->assertActiveUser($hqId, $assignment->toUserId, 'to_user_id');
        $this->assertActiveTeam($hqId, $assignment->toTeamId, 'to_team_id');

        $remindAt = $assignment->remindSpecified ? $assignment->remindAt : $current->remind_at;
        $dueAt = $assignment->dueSpecified ? $assignment->dueAt : $current->due_at;
        $this->assertReminderFitsDeadline($remindAt, $dueAt);
        if ($remindAt !== null && $assignment->toUserId === null) {
            throw $this->invalid('remind_at', 'task.reminder_needs_an_owner');
        }
    }

    public function validateAction(string $hqId, TaskRecord $current, TaskActionDto $action): void
    {
        if (in_array($current->status, [TaskStatus::COMPLETED, TaskStatus::CANCELLED], true)) {
            throw $this->invalid('*', 'task.closed_task_takes_no_action');
        }
        $changes = $action->task;
        if ($changes->status === TaskStatus::COMPLETED && trim((string) $changes->completionResult) === '') {
            throw $this->invalid('task.completion_result', 'task.finishing_needs_a_result');
        }

        $remindAt = $changes->remindSpecified ? $changes->remindAt : $current->remind_at;
        $dueAt = $changes->dueSpecified ? $changes->dueAt : $current->due_at;
        $this->assertReminderFitsDeadline($remindAt, $dueAt);
        // A closed task carries no live reminder, so finishing one clears it rather than refusing.
        if ($remindAt !== null && $current->assignee_id === null) {
            throw $this->invalid('task.remind_at', 'task.reminder_needs_an_owner');
        }

        $this->activityValidator->validateEmbedded($hqId, $action->activity);
    }

    /** A reminder is a nudge before the deadline; after it, it reminds nobody of anything. */
    private function assertReminderFitsDeadline(?DateTimeImmutable $remindAt, ?DateTimeImmutable $dueAt): void
    {
        if ($remindAt !== null && $dueAt !== null && $remindAt > $dueAt) {
            throw $this->invalid('remind_at', 'task.reminder_cannot_follow_the_deadline');
        }
    }

    /** Where the task is filed. An opportunity is always read through the customer that owns it. */
    private function assertFiling(string $hqId, ?string $customerId, ?string $opportunityId): void
    {
        if ($customerId !== null && ! $this->customerDirectory->existsForTenant($hqId, $customerId)) {
            throw $this->invalid('customer_id', 'task.select_a_customer_of_this_tenant');
        }
        if ($opportunityId === null) {
            return;
        }
        if ($customerId === null) {
            throw $this->invalid('customer_id', 'task.an_opportunity_task_names_its_customer');
        }
        if (! $this->opportunityDirectory->existsForCustomer($hqId, $customerId, $opportunityId)) {
            throw $this->invalid('opportunity_id', 'task.select_an_opportunity_of_the_same_customer');
        }
    }

    private function assertActiveUser(string $hqId, ?string $userId, string $field): void
    {
        if ($userId !== null && $this->userRepository->findByTenant($hqId, $userId)?->status !== 'ACTIVE') {
            throw $this->invalid($field, 'task.select_an_active_user_of_this_tenant');
        }
    }

    private function assertActiveTeam(string $hqId, ?string $teamId, string $field): void
    {
        if ($teamId !== null && ! $this->teamDirectory->activeExistsForTenant($hqId, $teamId)) {
            throw $this->invalid($field, 'task.select_an_active_team_of_this_tenant');
        }
    }

    /** @param string $messageKey Key into lang/<locale>/api.php. */
    private function invalid(string $field, string $messageKey): ApiException
    {
        return new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', [$field => [$messageKey]]);
    }
}
