<?php

declare(strict_types=1);

namespace Modules\CrmTask\Presentation\Mappers;

use DateTimeImmutable;
use DateTimeZone;
use Modules\CrmTask\Application\Dto\ActivityDraftDto;
use Modules\CrmTask\Application\Dto\TaskActionChangesDto;
use Modules\CrmTask\Application\Dto\TaskActionDto;
use Modules\CrmTask\Application\Dto\TaskAssignmentDto;
use Modules\CrmTask\Application\Dto\TaskDraftDto;
use Modules\CrmTask\Application\Dto\TaskListFiltersDto;
use Modules\CrmTask\Application\UseCases\AssignTask\AssignTaskCommand;
use Modules\CrmTask\Application\UseCases\CompleteTask\CompleteTaskCommand;
use Modules\CrmTask\Application\UseCases\CreateTask\CreateTaskCommand;
use Modules\CrmTask\Application\UseCases\ListTasks\ListTasksCommand;
use Modules\CrmTask\Application\UseCases\RecordTaskAction\RecordTaskActionCommand;
use Modules\CrmTask\Domain\Enums\ActivityChannel;
use Modules\CrmTask\Domain\Enums\ActivityDirection;
use Modules\CrmTask\Domain\Enums\ActivityType;
use Modules\CrmTask\Domain\Enums\CallOutcome;
use Modules\CrmTask\Domain\Enums\MeetingMode;
use Modules\CrmTask\Domain\Enums\TaskAssignmentEventType;
use Modules\CrmTask\Domain\Enums\TaskBucket;
use Modules\CrmTask\Domain\Enums\TaskPriority;
use Modules\CrmTask\Domain\Enums\TaskScope;
use Modules\CrmTask\Domain\Enums\TaskStatus;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final class TaskCommandMapper
{
    public static function listing(AuthenticatedPrincipal $actor, array $input): ListTasksCommand
    {
        return new ListTasksCommand($actor, new TaskListFiltersDto(
            scope: isset($input['scope']) ? TaskScope::from($input['scope']) : TaskScope::MINE,
            bucket: isset($input['bucket']) ? TaskBucket::from($input['bucket']) : null,
            search: $input['q'] ?? null,
        ));
    }

    public static function draft(AuthenticatedPrincipal $actor, array $input): CreateTaskCommand
    {
        return new CreateTaskCommand($actor, new TaskDraftDto(
            title: $input['title'],
            priority: isset($input['priority']) ? TaskPriority::from($input['priority']) : TaskPriority::MEDIUM,
            description: $input['description'] ?? null,
            dueAt: self::moment($input['due_at'] ?? null),
            remindAt: self::moment($input['remind_at'] ?? null),
            customerId: isset($input['customer_id']) ? (string) $input['customer_id'] : null,
            opportunityId: isset($input['opportunity_id']) ? (string) $input['opportunity_id'] : null,
            assigneeId: isset($input['assignee_id']) ? (string) $input['assignee_id'] : null,
            teamContextId: isset($input['team_context_id']) ? (string) $input['team_context_id'] : null,
            operationKey: $input['operation_key'] ?? null,
        ));
    }

    public static function completion(AuthenticatedPrincipal $actor, string $taskId, array $input): CompleteTaskCommand
    {
        return new CompleteTaskCommand($actor, $taskId, $input['completion_result']);
    }

    public static function assignment(AuthenticatedPrincipal $actor, string $taskId, array $input): AssignTaskCommand
    {
        // array_key_exists, not isset: an explicit null clears the field and must reach the handler.
        return new AssignTaskCommand($actor, $taskId, new TaskAssignmentDto(
            eventType: TaskAssignmentEventType::from($input['event_type']),
            toTeamId: isset($input['to_team_id']) ? (string) $input['to_team_id'] : null,
            toUserId: isset($input['to_user_id']) ? (string) $input['to_user_id'] : null,
            reason: $input['reason'] ?? null,
            dueAt: self::moment($input['due_at'] ?? null),
            dueSpecified: array_key_exists('due_at', $input),
            remindAt: self::moment($input['remind_at'] ?? null),
            remindSpecified: array_key_exists('remind_at', $input),
            expectedAssigneeId: isset($input['expected_assignee_id']) ? (string) $input['expected_assignee_id'] : null,
            expectedAssigneeSpecified: array_key_exists('expected_assignee_id', $input),
            operationKey: $input['operation_key'] ?? null,
        ));
    }

    public static function action(AuthenticatedPrincipal $actor, string $taskId, array $input): RecordTaskActionCommand
    {
        $activity = $input['activity'];
        $task = $input['task'] ?? [];

        return new RecordTaskActionCommand($actor, $taskId, new TaskActionDto(
            new ActivityDraftDto(
                type: ActivityType::from($activity['type']),
                occurredAt: self::moment($activity['occurred_at']),
                body: $activity['body'] ?? null,
                result: $activity['result'] ?? null,
                direction: isset($activity['direction']) ? ActivityDirection::from($activity['direction']) : null,
                contactCustomerId: isset($activity['contact_customer_id']) ? (string) $activity['contact_customer_id'] : null,
                contactValue: $activity['contact_value'] ?? null,
                channel: isset($activity['channel']) ? ActivityChannel::from($activity['channel']) : null,
                durationMinutes: isset($activity['duration_minutes']) ? (int) $activity['duration_minutes'] : null,
                callOutcome: isset($activity['call_outcome']) ? CallOutcome::from($activity['call_outcome']) : null,
                meetingMode: isset($activity['meeting_mode']) ? MeetingMode::from($activity['meeting_mode']) : null,
                location: $activity['location'] ?? null,
                meetingUrl: $activity['meeting_url'] ?? null,
                documentVersionId: isset($activity['document_version_id']) ? (string) $activity['document_version_id'] : null,
            ),
            new TaskActionChangesDto(
                status: isset($task['status']) ? TaskStatus::from($task['status']) : null,
                dueAt: self::moment($task['due_at'] ?? null),
                dueSpecified: array_key_exists('due_at', $task),
                remindAt: self::moment($task['remind_at'] ?? null),
                remindSpecified: array_key_exists('remind_at', $task),
                completionResult: $task['completion_result'] ?? null,
            ),
        ));
    }

    /** Wall-clock input is stored in UTC; the offset the client sent decides which instant that is. */
    private static function moment(?string $value): ?DateTimeImmutable
    {
        return $value === null ? null : (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
    }
}
