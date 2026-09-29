<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\UseCases\RecordActivity;

use Illuminate\Database\ConnectionInterface;
use Modules\CrmTask\Application\Contracts\ActivityValidatorInterface;
use Modules\CrmTask\Application\Contracts\TaskAccessGuardInterface;
use Modules\CrmTask\Application\Repositories\ActivityParticipantRepositoryInterface;
use Modules\CrmTask\Application\Repositories\ActivityRepositoryInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;

/**
 * Recording an interaction that is not the by-product of a task action: a call taken, a meeting held, a
 * note written against a customer, an opportunity or a task. The interaction and the colleagues who sat
 * in the meeting are written together or not at all.
 */
final readonly class RecordActivityHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private TaskAccessGuardInterface $accessGuard,
        private ActivityRepositoryInterface $activityRepository,
        private ActivityParticipantRepositoryInterface $activityParticipantRepository,
        private ActivityValidatorInterface $activityValidator,
    ) {}

    public function handle(RecordActivityCommand $command): RecordActivityResult
    {
        $hqId = $this->accessGuard->assertCanRecordActivity($command->actor);
        $draft = $command->activity;

        return $this->connection->transaction(function () use ($hqId, $command, $draft): RecordActivityResult {
            $filing = $this->activityValidator->validateStandalone($hqId, $draft, $command->filing);

            $at = $this->clock->now();
            $activity = $this->activityRepository->create([
                'hq_id' => $hqId,
                'type' => $draft->type->value,
                'occurred_at' => $draft->occurredAt,
                'customer_id' => $filing->customerId,
                'opportunity_id' => $filing->opportunityId,
                'task_id' => $filing->taskId,
                'body' => $draft->body,
                'result' => $draft->result,
                'contact_customer_id' => $draft->contactCustomerId,
                'direction' => $draft->direction?->value,
                'contact_value' => $draft->contactValue,
                'channel' => $draft->channel?->value,
                'duration_minutes' => $draft->durationMinutes,
                'call_outcome' => $draft->callOutcome?->value,
                'meeting_mode' => $draft->meetingMode?->value,
                'location' => $draft->location,
                'meeting_url' => $draft->meetingUrl,
                'document_version_id' => $draft->documentVersionId,
                'created_by' => $command->actor->userId,
                'created_at' => $at,
                'updated_at' => $at,
            ]);

            $participants = [];
            foreach ($draft->participants as $participant) {
                $participants[] = $this->activityParticipantRepository->create([
                    'hq_id' => $hqId,
                    'activity_id' => $activity->activity_id,
                    'user_id' => $participant->userId,
                    'minutes' => $participant->minutes,
                    'created_by' => $command->actor->userId,
                    'created_at' => $at,
                ]);
            }

            return new RecordActivityResult($activity, $participants);
        }, attempts: 3);
    }
}
