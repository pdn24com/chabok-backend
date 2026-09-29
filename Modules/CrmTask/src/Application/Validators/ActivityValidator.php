<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\Validators;

use Modules\CrmTask\Application\Contracts\ActivityValidatorInterface;
use Modules\CrmTask\Application\Dto\ActivityDraftDto;
use Modules\CrmTask\Application\Dto\ActivityFilingDto;
use Modules\CrmTask\Application\Ports\ActivityFilingDirectoryInterface;
use Modules\CrmTask\Application\Ports\CustomerDirectoryInterface;
use Modules\CrmTask\Application\Repositories\TaskRepositoryInterface;
use Modules\CrmTask\Domain\Enums\ActivityType;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;

/**
 * The rules of one recorded interaction, shared by the two ways of recording it: beside a task action
 * and on its own. They mirror the database guarantees (which SQLite does not carry) so that a bad
 * submission is a named 422 rather than a failed insert.
 */
final readonly class ActivityValidator implements ActivityValidatorInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private CustomerDirectoryInterface $customerDirectory,
        private ActivityFilingDirectoryInterface $activityFilingDirectory,
        private TaskRepositoryInterface $taskRepository,
    ) {}

    public function validateEmbedded(string $hqId, ActivityDraftDto $activity): void
    {
        $this->assertDetailFitsItsType($hqId, $activity, 'activity.');
    }

    public function validateStandalone(string $hqId, ActivityDraftDto $activity, ActivityFilingDto $filing): ActivityFilingDto
    {
        // A referral is the trace of a task changing hands; only the assignment event can write one.
        if ($activity->type === ActivityType::REFERRAL) {
            throw $this->invalid('type', 'activity.referral_is_produced_by_an_assignment');
        }
        $this->assertDetailFitsItsType($hqId, $activity, '');
        $this->assertContactIsAPerson($hqId, $activity);
        $this->assertCompleteForItsType($activity);

        return $this->resolveFiling($hqId, $filing);
    }

    /**
     * Each kind of interaction carries its own detail and nothing else: a call has an outcome, a meeting
     * has a place, a note has neither. The columns behind the unused fields stay null by design.
     */
    private function assertDetailFitsItsType(string $hqId, ActivityDraftDto $activity, string $prefix): void
    {
        $type = $activity->type;
        if ($activity->callOutcome !== null && $type !== ActivityType::CALL) {
            throw $this->invalid($prefix.'call_outcome', 'task.call_details_belong_to_a_call');
        }
        if ($activity->durationMinutes !== null && ! in_array($type, [ActivityType::CALL, ActivityType::MEETING], true)) {
            throw $this->invalid($prefix.'duration_minutes', 'activity.duration_belongs_to_calls_and_meetings');
        }
        if ($activity->direction !== null && ! in_array($type, [ActivityType::CALL, ActivityType::MESSAGE], true)) {
            throw $this->invalid($prefix.'direction', 'activity.direction_belongs_to_calls_and_messages');
        }
        foreach (['meeting_mode' => $activity->meetingMode, 'location' => $activity->location, 'meeting_url' => $activity->meetingUrl] as $field => $value) {
            if ($value !== null && $type !== ActivityType::MEETING) {
                throw $this->invalid($prefix.$field, 'task.meeting_details_belong_to_a_meeting');
            }
        }
        if ($activity->documentVersionId !== null && $type !== ActivityType::DOCUMENT_SENT) {
            throw $this->invalid($prefix.'document_version_id', 'task.a_document_belongs_to_a_document_action');
        }
        if ($activity->participants !== [] && $type !== ActivityType::MEETING) {
            throw $this->invalid($prefix.'participants', 'activity.participants_belong_to_a_meeting');
        }
        if ($activity->contactCustomerId !== null
            && ! $this->customerDirectory->existsForTenant($hqId, $activity->contactCustomerId)) {
            throw $this->invalid($prefix.'contact_customer_id', 'task.select_a_customer_of_this_tenant');
        }
        $this->assertParticipants($hqId, $activity, $prefix);
    }

    /** The people at a meeting are colleagues of this tenant, each named once. */
    private function assertParticipants(string $hqId, ActivityDraftDto $activity, string $prefix): void
    {
        $seen = [];
        foreach ($activity->participants as $index => $participant) {
            $field = $prefix.'participants.'.$index.'.user_id';
            if (isset($seen[$participant->userId])) {
                throw $this->invalid($field, 'activity.a_participant_is_named_once');
            }
            $seen[$participant->userId] = true;
            if ($this->userRepository->findByTenant($hqId, $participant->userId)?->status !== 'ACTIVE') {
                throw $this->invalid($field, 'task.select_an_active_user_of_this_tenant');
            }
        }
    }

    /** The person spoken to is a PERSON record; a company is never the voice on the other end. */
    private function assertContactIsAPerson(string $hqId, ActivityDraftDto $activity): void
    {
        if ($activity->contactCustomerId !== null
            && ! $this->activityFilingDirectory->personExistsForTenant($hqId, $activity->contactCustomerId)) {
            throw $this->invalid('contact_customer_id', 'activity.select_a_person_as_contact');
        }
    }

    /** What a kind of interaction cannot exist without: a call has a direction, a number and an outcome. */
    private function assertCompleteForItsType(ActivityDraftDto $activity): void
    {
        $required = match ($activity->type) {
            ActivityType::CALL => ['direction' => $activity->direction, 'contact_value' => $activity->contactValue, 'call_outcome' => $activity->callOutcome],
            ActivityType::MESSAGE => ['direction' => $activity->direction, 'channel' => $activity->channel, 'contact_value' => $activity->contactValue],
            ActivityType::MEETING => ['meeting_mode' => $activity->meetingMode],
            ActivityType::NOTE => ['body' => $activity->body],
            default => [],
        };
        foreach ($required as $field => $value) {
            if ($value === null || (is_string($value) && trim($value) === '')) {
                throw $this->invalid($field, 'activity.required_for_this_type');
            }
        }
        if ($activity->type === ActivityType::MEETING && $activity->location === null && $activity->meetingUrl === null) {
            throw $this->invalid('location', 'activity.a_meeting_needs_a_place_or_a_link');
        }
    }

    /**
     * Resolves where the interaction is filed. A task pins the customer and opportunity it belongs to and
     * the interaction never contradicts it; an opportunity names its own customer. Whatever the request
     * left out is taken from there.
     */
    private function resolveFiling(string $hqId, ActivityFilingDto $filing): ActivityFilingDto
    {
        $customerId = $filing->customerId;
        $opportunityId = $filing->opportunityId;
        $taskId = $filing->taskId;
        if ($customerId === null && $opportunityId === null && $taskId === null) {
            throw $this->invalid('customer_id', 'activity.attach_to_a_customer_an_opportunity_or_a_task');
        }
        if ($customerId !== null && ! $this->customerDirectory->existsForTenant($hqId, $customerId)) {
            throw $this->invalid('customer_id', 'task.select_a_customer_of_this_tenant');
        }

        if ($taskId !== null) {
            $task = $this->taskRepository->findForTenant($hqId, $taskId)
                ?? throw $this->invalid('task_id', 'activity.select_a_task_of_this_tenant');
            $taskCustomerId = $task->customer_id === null ? null : (string) $task->customer_id;
            $taskOpportunityId = $task->opportunity_id === null ? null : (string) $task->opportunity_id;
            if ($customerId !== null && $customerId !== $taskCustomerId) {
                throw $this->invalid('customer_id', 'activity.the_task_is_filed_against_another_customer');
            }
            if ($opportunityId !== null && $opportunityId !== $taskOpportunityId) {
                throw $this->invalid('opportunity_id', 'activity.the_task_is_filed_against_another_opportunity');
            }
            $customerId ??= $taskCustomerId;
            $opportunityId ??= $taskOpportunityId;
        }

        if ($opportunityId !== null) {
            $owner = $this->activityFilingDirectory->customerOfOpportunity($hqId, $opportunityId)
                ?? throw $this->invalid('opportunity_id', 'activity.select_an_opportunity_of_this_tenant');
            if ($customerId !== null && $customerId !== $owner) {
                throw $this->invalid('opportunity_id', 'task.select_an_opportunity_of_the_same_customer');
            }
            $customerId ??= $owner;
        }

        return new ActivityFilingDto($customerId, $opportunityId, $taskId);
    }

    /** @param string $messageKey Key into lang/<locale>/api.php. */
    private function invalid(string $field, string $messageKey): ApiException
    {
        return new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', [$field => [$messageKey]]);
    }
}
