<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use Mockery;
use Modules\CrmTask\Application\Dto\ActivityDraftDto;
use Modules\CrmTask\Application\Dto\TaskActionChangesDto;
use Modules\CrmTask\Application\Dto\TaskActionDto;
use Modules\CrmTask\Application\Dto\TaskAssignmentDto;
use Modules\CrmTask\Application\Dto\TaskDraftDto;
use Modules\CrmTask\Application\Ports\ActivityFilingDirectoryInterface;
use Modules\CrmTask\Application\Ports\CustomerDirectoryInterface;
use Modules\CrmTask\Application\Ports\OpportunityDirectoryInterface;
use Modules\CrmTask\Application\Ports\TeamDirectoryInterface;
use Modules\CrmTask\Application\Repositories\TaskRepositoryInterface;
use Modules\CrmTask\Application\Validators\ActivityValidator;
use Modules\CrmTask\Application\Validators\TaskValidator;
use Modules\CrmTask\Domain\Enums\ActivityType;
use Modules\CrmTask\Domain\Enums\CallOutcome;
use Modules\CrmTask\Domain\Enums\TaskAssignmentEventType;
use Modules\CrmTask\Domain\Enums\TaskPriority;
use Modules\CrmTask\Domain\Enums\TaskStatus;
use Modules\CrmTask\Infrastructure\Persistence\Models\TaskRecord;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;
use Tests\TestCase;

/** The task rules, judged without a database: what may be raised, moved and recorded. */
final class TaskRulesTest extends TestCase
{
    private const ACTOR = '12';

    public function test_a_task_may_be_raised_against_a_customer_with_an_owner_and_a_reminder(): void
    {
        $this->validator()->validateDraft('1', new TaskDraftDto(
            title: 'پیگیری پیشنهاد',
            priority: TaskPriority::HIGH,
            dueAt: new DateTimeImmutable('2026-10-02T10:00:00Z'),
            remindAt: new DateTimeImmutable('2026-10-02T09:00:00Z'),
            customerId: '11',
            opportunityId: '3',
            assigneeId: '12',
            teamContextId: '5',
        ));

        $this->expectNotToPerformAssertions();
    }

    /** @param array<string, mixed> $overrides */
    #[\PHPUnit\Framework\Attributes\DataProvider('rejectedDrafts')]
    public function test_an_incoherent_draft_names_the_field_that_is_wrong(array $overrides, string $field, string $messageKey): void
    {
        $draft = new TaskDraftDto(...array_replace([
            'title' => 'پیگیری', 'priority' => TaskPriority::MEDIUM, 'assigneeId' => '12',
        ], $overrides));

        $this->assertRefused(fn () => $this->validator()->validateDraft('1', $draft), $field, $messageKey);
    }

    public static function rejectedDrafts(): array
    {
        return [
            'reminder after the deadline' => [[
                'dueAt' => new DateTimeImmutable('2026-10-02T10:00:00Z'),
                'remindAt' => new DateTimeImmutable('2026-10-02T11:00:00Z'),
            ], 'remind_at', 'task.reminder_cannot_follow_the_deadline'],
            'reminder without an owner' => [[
                'assigneeId' => null, 'remindAt' => new DateTimeImmutable('2026-10-02T09:00:00Z'),
            ], 'remind_at', 'task.reminder_needs_an_owner'],
            'unknown customer' => [['customerId' => '99'], 'customer_id', 'task.select_a_customer_of_this_tenant'],
            'opportunity without its customer' => [['opportunityId' => '3'], 'customer_id', 'task.an_opportunity_task_names_its_customer'],
            'opportunity of another customer' => [
                ['customerId' => '11', 'opportunityId' => '99'],
                'opportunity_id', 'task.select_an_opportunity_of_the_same_customer',
            ],
            'inactive owner' => [['assigneeId' => '77'], 'assignee_id', 'task.select_an_active_user_of_this_tenant'],
            'unknown team' => [['teamContextId' => '99'], 'team_context_id', 'task.select_an_active_team_of_this_tenant'],
        ];
    }

    public function test_handing_work_on_is_explained_and_taking_it_is_not(): void
    {
        $task = $this->task();

        $this->assertRefused(
            fn () => $this->validator()->validateAssignment('1', $task, new TaskAssignmentDto(
                eventType: TaskAssignmentEventType::REFER, toUserId: '13'), self::ACTOR),
            'reason', 'task.moving_work_needs_a_reason');

        $this->validator()->validateAssignment('1', $task, new TaskAssignmentDto(
            eventType: TaskAssignmentEventType::CLAIM, toUserId: self::ACTOR), self::ACTOR);
    }

    public function test_claiming_takes_the_task_for_the_actor_and_nobody_else(): void
    {
        $this->assertRefused(
            fn () => $this->validator()->validateAssignment('1', $this->task(), new TaskAssignmentDto(
                eventType: TaskAssignmentEventType::CLAIM, toUserId: '13'), self::ACTOR),
            'to_user_id', 'task.claim_takes_the_task_for_the_actor');
    }

    public function test_a_move_against_a_stale_owner_is_a_conflict(): void
    {
        try {
            $this->validator()->validateAssignment('1', $this->task(), new TaskAssignmentDto(
                eventType: TaskAssignmentEventType::REASSIGN, toUserId: '13', reason: 'بررسی فنی',
                expectedAssigneeId: '99', expectedAssigneeSpecified: true), self::ACTOR);
            self::fail('The move should have been refused.');
        } catch (ApiException $exception) {
            self::assertSame(409, $exception->httpStatus);
            self::assertSame('task.assignment_changed_since_loaded', $exception->messageKey);
        }
    }

    public function test_a_closed_task_is_neither_moved_nor_acted_on(): void
    {
        $closed = $this->task(['status' => TaskStatus::COMPLETED->value]);

        $this->assertRefused(
            fn () => $this->validator()->validateAssignment('1', $closed, new TaskAssignmentDto(
                eventType: TaskAssignmentEventType::CLAIM, toUserId: self::ACTOR), self::ACTOR),
            '*', 'task.closed_task_cannot_be_moved');

        $this->assertRefused(fn () => $this->validator()->validateAction('1', $closed, $this->action()),
            '*', 'task.closed_task_takes_no_action');
    }

    public function test_finishing_through_an_action_records_what_came_of_it(): void
    {
        $this->assertRefused(
            fn () => $this->validator()->validateAction('1', $this->task(), $this->action(
                changes: new TaskActionChangesDto(status: TaskStatus::COMPLETED))),
            'task.completion_result', 'task.finishing_needs_a_result');
    }

    public function test_the_detail_of_one_kind_of_interaction_never_rides_on_another(): void
    {
        $this->assertRefused(
            fn () => $this->validator()->validateAction('1', $this->task(), $this->action(
                type: ActivityType::NOTE, callOutcome: CallOutcome::ANSWERED)),
            'activity.call_outcome', 'task.call_details_belong_to_a_call');

        $this->assertRefused(
            fn () => $this->validator()->validateAction('1', $this->task(), $this->action(
                type: ActivityType::CALL, location: 'دفتر مرکزی')),
            'activity.location', 'task.meeting_details_belong_to_a_meeting');
    }

    public function test_a_call_carries_its_own_outcome_without_complaint(): void
    {
        $this->validator()->validateAction('1', $this->task(), $this->action(
            type: ActivityType::CALL, callOutcome: CallOutcome::ANSWERED));

        $this->expectNotToPerformAssertions();
    }

    private function assertRefused(callable $act, string $field, string $messageKey): void
    {
        try {
            $act();
            self::fail("Expected a refusal naming {$field}.");
        } catch (ApiException $exception) {
            self::assertSame([$field => [$messageKey]], $exception->fieldErrors);
            self::assertSame(422, $exception->httpStatus);
        }
    }

    private function action(
        ActivityType $type = ActivityType::NOTE,
        ?CallOutcome $callOutcome = null,
        ?string $location = null,
        ?TaskActionChangesDto $changes = null,
    ): TaskActionDto {
        return new TaskActionDto(
            new ActivityDraftDto($type, new DateTimeImmutable('2026-09-29T10:00:00Z'),
                callOutcome: $callOutcome, location: $location),
            $changes ?? new TaskActionChangesDto,
        );
    }

    /** @param array<string, mixed> $attributes */
    private function task(array $attributes = []): TaskRecord
    {
        $task = new TaskRecord;
        $task->setRawAttributes(array_replace([
            'id' => 44, 'hq_id' => 1, 'status' => TaskStatus::OPEN->value,
            'assignee_id' => self::ACTOR, 'due_at' => null, 'remind_at' => null,
        ], $attributes), sync: true);

        return $task;
    }

    private function validator(): TaskValidator
    {
        $users = Mockery::mock(UserRepositoryInterface::class);
        $users->shouldReceive('findByTenant')->andReturnUsing(function (string $hqId, string $userId): ?UserRecord {
            if (! in_array($userId, ['12', '13', '77'], true)) {
                return null;
            }
            $user = new UserRecord;
            $user->setRawAttributes(['id' => (int) $userId, 'status' => $userId === '77' ? 'INVITED' : 'ACTIVE'], sync: true);

            return $user;
        });

        $customers = Mockery::mock(CustomerDirectoryInterface::class);
        $customers->shouldReceive('existsForTenant')->andReturnUsing(fn (string $hqId, string $id): bool => $id === '11');

        $opportunities = Mockery::mock(OpportunityDirectoryInterface::class);
        $opportunities->shouldReceive('existsForCustomer')
            ->andReturnUsing(fn (string $hqId, string $customerId, string $id): bool => $customerId === '11' && $id === '3');

        $teams = Mockery::mock(TeamDirectoryInterface::class);
        $teams->shouldReceive('activeExistsForTenant')->andReturnUsing(fn (string $hqId, string $id): bool => $id === '5');

        $activities = new ActivityValidator(
            $users,
            $customers,
            Mockery::mock(ActivityFilingDirectoryInterface::class),
            Mockery::mock(TaskRepositoryInterface::class),
        );

        return new TaskValidator($users, $customers, $opportunities, $teams, $activities);
    }
}
